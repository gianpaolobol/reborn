<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Infrastructure\MarketplaceModelSearchRepository;
use Reborn\Marketplace\Infrastructure\MarketplaceWebSearchAdapter;

final class SearchReplacementModelService
{
    public function __construct(
        private readonly MarketplaceModelSearchRepository $repository,
        private readonly MarketplaceWebSearchAdapter $webSearch,
        private readonly array $config,
    ) {
    }

    /** @param array<string, mixed> $recognitionResult @return array<string, mixed> */
    public function search(string $repairCaseId, string $recognitionJobId, array $recognitionResult): array
    {
        if (empty($this->config['enabled'])) {
            return ['enabled' => false, 'status' => 'disabled'];
        }

        if (!$this->isRecognizedEnough($recognitionResult)) {
            return [
                'enabled' => true,
                'status' => 'skipped_needs_better_recognition',
                'message' => 'Servono prima un pezzo riconosciuto, un codice o una descrizione piu precisa.',
                'next_step' => 'ask_more_photos',
            ];
        }

        $queryPack = $this->buildQueries($recognitionResult);
        if ($queryPack['queries'] === []) {
            return [
                'enabled' => true,
                'status' => 'skipped_no_search_terms',
                'message' => 'Il riconoscimento non contiene ancora termini utili per cercare un file STL.',
                'next_step' => 'ask_more_photos',
            ];
        }

        $searchId = $this->repository->create($repairCaseId, $recognitionJobId, $queryPack);
        $candidates = [];

        foreach ($this->enabledSources() as $source) {
            foreach ($this->staticSearchCandidates($source, $queryPack) as $candidate) {
                $candidates[$this->fingerprint((string) $candidate['url'])] = $candidate;
            }

            foreach ($this->webSearch->search($source, $this->queriesForSource($source, $queryPack)) as $candidate) {
                $candidates[$this->fingerprint((string) $candidate['url'])] = $candidate;
            }
        }

        $candidates = array_values($candidates);
        usort($candidates, static fn(array $a, array $b): int => ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0)));
        $candidates = array_slice($candidates, 0, max(3, min(15, (int) ($this->config['max_results'] ?? 10))));

        foreach ($candidates as $candidate) {
            $this->repository->addCandidate($searchId, $candidate);
        }

        $best = $candidates[0] ?? null;
        $minScore = max(40, min(90, (int) ($this->config['min_score'] ?? 70)));
        $hasDownloadCandidate = (bool) array_filter($candidates, static fn(array $candidate): bool => !empty($candidate['download_available']) && ((int) ($candidate['score'] ?? 0)) >= 70);
        $status = $hasDownloadCandidate ? 'possible_file_found' : (((int) ($best['score'] ?? 0)) >= $minScore ? 'possible_model_page_found' : 'search_links_ready');

        $summary = [
            'status' => $status,
            'best_score' => (int) ($best['score'] ?? 0),
            'candidate_count' => count($candidates),
            'sources' => array_values(array_unique(array_map(static fn(array $candidate): string => (string) ($candidate['source'] ?? 'web'), $candidates))),
        ];
        $this->repository->complete($searchId, $status, $summary);

        return [
            'enabled' => true,
            'status' => $status,
            'search_id' => $searchId,
            'query_terms' => $queryPack['terms'],
            'queries' => $queryPack['queries'],
            'best_candidate' => $best,
            'candidates' => array_slice($candidates, 0, 8),
            'summary' => $summary,
            'next_step' => $hasDownloadCandidate ? 'manual_verify_before_download_or_print' : 'assisted_request_if_no_model_match',
            'safety_note' => 'I link sono candidati da verificare manualmente: licenza, compatibilita dimensionale, materiale e scaricabilita non sono garantiti.',
        ];
    }

    /** @param array<string, mixed> $result */
    private function isRecognizedEnough(array $result): bool
    {
        $status = strtolower((string) ($result['identification']['status'] ?? ''));
        $mode = strtolower((string) ($result['recognition_mode'] ?? ''));
        $providerStatus = strtolower((string) ($result['ai_provider']['status'] ?? ''));
        if ($providerStatus === 'error_fallback' || str_starts_with($mode, 'fallback_after_') || $mode === 'live_provider_unavailable') {
            return false;
        }

        if ($status === 'recognized') {
            return true;
        }

        $terms = $this->extractTerms($result);
        return count($terms) >= 2;
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function buildQueries(array $result): array
    {
        $terms = $this->extractTerms($result);
        $partNumber = $this->cleanTerm((string) ($result['identification']['part_number'] ?? ''));
        $commercialName = $this->cleanTerm((string) ($result['identification']['commercial_name'] ?? ''));
        $partIt = $this->cleanTerm((string) ($result['part_spec']['name_it'] ?? ''));
        $partEn = $this->cleanTerm((string) ($result['part_spec']['name_en'] ?? ''));
        $family = $this->cleanTerm((string) ($result['replacement_part_brief']['part_family'] ?? ''));
        $function = $this->cleanTerm((string) ($result['replacement_part_brief']['probable_function'] ?? ''));
        $brand = $this->firstClean((array) ($result['identification']['possible_brands'] ?? []));
        $model = $this->firstClean((array) ($result['identification']['possible_models'] ?? []));

        $core = $commercialName ?: ($partEn ?: ($partIt ?: ($family ?: implode(' ', array_slice($terms, 0, 4)))));
        $prefix = trim(implode(' ', array_filter([$brand, $model, $partNumber])));
        $base = trim($prefix . ' ' . $core);
        if ($base === '') {
            $base = trim(implode(' ', array_slice($terms, 0, 5)));
        }

        $queries = [];
        foreach (array_filter([
            trim($partNumber . ' ' . $core . ' STL'),
            trim($base . ' STL'),
            trim($base . ' 3D print replacement'),
            trim($base . ' 3MF'),
            trim($partIt . ' ricambio stampa 3D STL'),
            trim($family . ' replacement part STL'),
        ]) as $query) {
            $query = $this->normalizeSpaces($query);
            if ($query !== '' && strlen($query) >= 4) {
                $queries[$query] = $query;
            }
        }

        if (count($queries) < 2 && $function !== '') {
            $queries[$this->normalizeSpaces($function . ' STL')] = $this->normalizeSpaces($function . ' STL');
        }

        return [
            'terms' => $terms,
            'part_number' => $partNumber,
            'brand' => $brand,
            'model' => $model,
            'core_name' => $core,
            'queries' => array_values($queries),
        ];
    }

    /** @param array<string, mixed> $result @return list<string> */
    private function extractTerms(array $result): array
    {
        $values = [
            $result['identification']['part_number'] ?? '',
            $result['identification']['commercial_name'] ?? '',
            $result['part_spec']['name_it'] ?? '',
            $result['part_spec']['name_en'] ?? '',
            $result['replacement_part_brief']['part_family'] ?? '',
            $result['object_guess']['label'] ?? '',
        ];

        foreach (['possible_brands', 'possible_models', 'visible_text'] as $key) {
            foreach ((array) ($result['identification'][$key] ?? []) as $value) {
                $values[] = $value;
            }
        }

        foreach ((array) ($result['part_spec']['compatibility_clues'] ?? []) as $value) {
            $values[] = $value;
        }

        $terms = [];
        foreach ($values as $value) {
            $clean = $this->cleanTerm((string) $value);
            if ($clean === '' || $this->isGenericTerm($clean)) {
                continue;
            }
            $terms[$clean] = $clean;
        }

        return array_values($terms);
    }

    /** @return list<string> */
    private function enabledSources(): array
    {
        $sources = [];
        foreach (['thingiverse', 'printables', 'makerworld', 'web_stl'] as $source) {
            $enabled = $this->config[$source]['enabled'] ?? true;
            if ($enabled) {
                $sources[] = $source;
            }
        }
        return $sources;
    }

    /** @param array<string, mixed> $queryPack @return list<array<string, mixed>> */
    private function staticSearchCandidates(string $source, array $queryPack): array
    {
        $query = (string) ($queryPack['queries'][0] ?? implode(' ', array_slice((array) $queryPack['terms'], 0, 5)));
        if ($query === '') {
            return [];
        }

        $encoded = rawurlencode($query);
        return match ($source) {
            'thingiverse' => [[
                'source' => 'thingiverse',
                'title' => 'Cerca su Thingiverse: ' . $query,
                'url' => 'https://www.thingiverse.com/search?q=' . $encoded . '&type=things&sort=relevant',
                'thumbnail_url' => '',
                'score' => 58,
                'license' => '',
                'download_available' => false,
                'file_type' => '',
                'match_reason' => 'Link di ricerca mirato su Thingiverse generato dai riferimenti AI del pezzo.',
            ]],
            'printables' => [[
                'source' => 'printables',
                'title' => 'Cerca su Printables: ' . $query,
                'url' => 'https://www.printables.com/search/models?q=' . $encoded,
                'thumbnail_url' => '',
                'score' => 56,
                'license' => '',
                'download_available' => false,
                'file_type' => '',
                'match_reason' => 'Link di ricerca mirato su Printables generato dai riferimenti AI del pezzo.',
            ]],
            'makerworld' => [[
                'source' => 'makerworld',
                'title' => 'Cerca su MakerWorld: ' . $query,
                'url' => 'https://makerworld.com/en/search/models?keyword=' . $encoded,
                'thumbnail_url' => '',
                'score' => 55,
                'license' => '',
                'download_available' => false,
                'file_type' => '',
                'match_reason' => 'Link di ricerca mirato su MakerWorld generato dai riferimenti AI del pezzo.',
            ]],
            'web_stl' => [[
                'source' => 'web_stl',
                'title' => 'Cerca file STL/3MF direttamente sul web: ' . $query,
                'url' => 'https://duckduckgo.com/?q=' . rawurlencode($query . ' filetype:stl OR filetype:3mf OR filetype:obj'),
                'thumbnail_url' => '',
                'score' => 54,
                'license' => '',
                'download_available' => false,
                'file_type' => '',
                'match_reason' => 'Link web mirato per trovare file STL, 3MF o OBJ scaricabili.',
            ]],
            default => [],
        };
    }

    /** @param array<string, mixed> $queryPack @return list<string> */
    private function queriesForSource(string $source, array $queryPack): array
    {
        $queries = [];
        foreach ((array) ($queryPack['queries'] ?? []) as $query) {
            $query = (string) $query;
            if ($source === 'thingiverse') {
                $queries[] = 'site:thingiverse.com ' . $query;
            } elseif ($source === 'printables') {
                $queries[] = 'site:printables.com/model ' . $query;
            } elseif ($source === 'makerworld') {
                $queries[] = 'site:makerworld.com ' . $query;
            } elseif ($source === 'web_stl') {
                $queries[] = $query . ' filetype:stl OR filetype:3mf OR filetype:obj';
            }
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /** @param list<mixed> $values */
    private function firstClean(array $values): string
    {
        foreach ($values as $value) {
            $clean = $this->cleanTerm((string) $value);
            if ($clean !== '' && !$this->isGenericTerm($clean)) {
                return $clean;
            }
        }
        return '';
    }

    private function cleanTerm(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}\s\.\-\/]/u', ' ', $value) ?? '';
        $value = $this->normalizeSpaces($value);
        return substr($value, 0, 90);
    }

    private function normalizeSpaces(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function isGenericTerm(string $term): bool
    {
        $value = strtolower($term);
        return in_array($value, [
            'component to confirm',
            'componente da confermare',
            'componente da immagine',
            'id non riconosciuta',
            'unrecognized id',
            'repairable component / unknown object family',
        ], true);
    }

    private function fingerprint(string $value): string
    {
        return sha1(strtolower(trim($value)));
    }
}
