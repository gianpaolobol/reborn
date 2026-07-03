<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

final class ModelLibraryMatchService
{
    public function __construct(private readonly string $baseDir)
    {
    }

    /**
     * Exact image-hash match: same uploaded image can have several compatible models.
     *
     * @param list<string> $sha256s
     * @return list<array<string,mixed>>
     */
    public function findMatchesByImageHashes(array $sha256s, int $limit = 10): array
    {
        $sha256s = $this->normalizeSha256s($sha256s);
        if ($sha256s === []) {
            return [];
        }

        $matches = [];
        foreach ($this->readRecords() as $record) {
            $recordHashes = $this->recordImageHashes($record);
            if ($recordHashes === []) {
                continue;
            }

            $overlap = array_intersect($sha256s, $recordHashes);
            if ($overlap === []) {
                continue;
            }

            $score = count($overlap) === count($sha256s) ? 100 : 98;
            $matches[] = $this->formatMatch($record, $score);
        }

        return $this->sortAndLimit($matches, $limit);
    }

    /**
     * Product-level match: same object from another viewpoint after AI recognition.
     *
     * @param array<string,mixed> $result
     * @return list<array<string,mixed>>
     */
    public function findMatchesByRecognitionResult(array $result, int $limit = 10): array
    {
        $queryTokens = $this->lookupTokens($this->recognitionLookupText($result));
        if ($queryTokens === []) {
            return [];
        }

        $matches = [];
        foreach ($this->readRecords() as $record) {
            $recordTokens = $this->lookupTokens($this->recordLookupText($record));
            if ($recordTokens === []) {
                continue;
            }

            $overlap = count(array_intersect($queryTokens, $recordTokens));
            if ($overlap < 2) {
                continue;
            }

            $score = (int) round(min(92, ($overlap / max(4, count($queryTokens))) * 100));
            if ($this->hasCodeTokenOverlap($queryTokens, $recordTokens)) {
                $score = max($score, 88);
            }

            if ($score < 45) {
                continue;
            }

            $matches[] = $this->formatMatch($record, $score);
        }

        return $this->sortAndLimit($matches, $limit);
    }

    /**
     * @param list<string> $sha256s
     * @return array<string,mixed>|null
     */
    public function buildRecognitionResultFromImageHashes(array $sha256s): ?array
    {
        $matches = $this->findMatchesByImageHashes($sha256s, 10);
        if ($matches === []) {
            return null;
        }

        $record = $matches[0]['_record'] ?? null;
        if (!is_array($record)) {
            return null;
        }

        $summary = json_decode((string) ($record['recognition_summary_json'] ?? '{}'), true);
        if (!is_array($summary) || $summary === []) {
            return null;
        }

        $part = trim((string) ($summary['part'] ?? $summary['identification']['commercial_name'] ?? $summary['part_spec']['name_it'] ?? ''));
        if ($part === '') {
            $part = 'id non riconosciuta';
        }

        $confidenceRaw = $summary['confidence'] ?? 98;
        $confidence = is_numeric($confidenceRaw) ? (float) $confidenceRaw : 98.0;
        $objectConfidence = $confidence > 1 ? ($confidence / 100) : $confidence;
        $identification = is_array($summary['identification'] ?? null) ? $summary['identification'] : [];
        $partSpec = is_array($summary['part_spec'] ?? null) ? $summary['part_spec'] : [];
        $brief = is_array($summary['replacement_part_brief'] ?? null) ? $summary['replacement_part_brief'] : [];

        if (($identification['status'] ?? '') === '') {
            $identification['status'] = 'recognized';
        }
        if (($identification['commercial_name'] ?? '') === '') {
            $identification['commercial_name'] = $part;
        }
        if (($partSpec['name_it'] ?? '') === '') {
            $partSpec['name_it'] = $part;
        }

        return [
            'recognition_mode' => 'reused_image_match',
            'source' => 'reborn_library_cache',
            'identification' => $identification,
            'part_spec' => $partSpec,
            'replacement_part_brief' => $brief,
            'object_guess' => [
                'label' => $part,
                'confidence' => $objectConfidence,
                'object_context' => (string) ($partSpec['appliance_context'] ?? $brief['probable_function'] ?? ''),
            ],
            'quality_gate' => [
                'quality_score' => $confidence > 1 ? (int) round($confidence) : (int) round($confidence * 100),
                'verification_status' => 'reused_image_match',
            ],
            'recommended_next_step' => [
                'path' => 'model_library_match',
                'reason' => 'Ho trovato uno o più modelli già presenti nella libreria Re-born.',
            ],
            'repair_notes' => ['Risultato recuperato dalla libreria Re-born tramite hash immagine.'],
            'library_matches' => $this->publicMatches($matches),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function readRecords(): array
    {
        $jsonl = $this->baseDir . DIRECTORY_SEPARATOR . 'contributions.jsonl';
        if (!is_file($jsonl)) {
            return [];
        }

        $records = [];
        $lines = file($jsonl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if (is_array($record)) {
                $records[] = $record;
            }
        }
        return $records;
    }

    /** @param list<string> $sha256s @return list<string> */
    private function normalizeSha256s(array $sha256s): array
    {
        $values = array_map(static fn ($value): string => trim((string) $value), $sha256s);
        return array_values(array_unique(array_filter($values)));
    }

    /** @return list<string> */
    private function recordImageHashes(array $record): array
    {
        $decoded = json_decode((string) ($record['image_fingerprints_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }
        $hashes = [];
        foreach ($decoded as $fingerprint) {
            if (is_array($fingerprint) && !empty($fingerprint['sha256'])) {
                $hashes[] = trim((string) $fingerprint['sha256']);
            }
        }
        return $this->normalizeSha256s($hashes);
    }

    /** @param list<array<string,mixed>> $matches @return list<array<string,mixed>> */
    private function publicMatches(array $matches): array
    {
        return array_map(static function (array $match): array {
            unset($match['_record']);
            return $match;
        }, $matches);
    }

    /** @param list<array<string,mixed>> $matches @return list<array<string,mixed>> */
    private function sortAndLimit(array $matches, int $limit): array
    {
        usort($matches, static function (array $a, array $b): int {
            $scoreDiff = ((int) ($b['match_score'] ?? 0)) <=> ((int) ($a['match_score'] ?? 0));
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($matches, 0, max(1, $limit));
    }

    /** @return array<string,mixed> */
    private function formatMatch(array $record, int $score): array
    {
        $file = is_array($record['file'] ?? null) ? $record['file'] : [];
        $id = (string) ($record['id'] ?? '');
        $stats = $this->statsFor($id);

        return [
            'id' => $id,
            'title' => (string) ($record['title'] ?? $record['query'] ?? 'Modello 3D in libreria'),
            'query' => (string) ($record['query'] ?? ''),
            'source' => (string) ($record['source'] ?? ''),
            'url' => (string) ($record['url'] ?? ''),
            'file_name' => (string) ($file['original_name'] ?? ''),
            'status' => (string) ($record['status'] ?? 'pending_review'),
            'match_score' => $score,
            'download_count' => (int) ($stats['download_count'] ?? 0),
            'preference_count' => (int) ($stats['preference_count'] ?? 0),
            'download_url' => !empty($file['storage_path']) ? '/api/v1/model-library/files/' . rawurlencode($id) : '',
            'created_at' => (string) ($record['created_at'] ?? ''),
            '_record' => $record,
        ];
    }

    /** @return array<string,int> */
    private function statsFor(string $id): array
    {
        if ($id === '') {
            return ['download_count' => 0, 'preference_count' => 0];
        }

        $statsFile = $this->baseDir . DIRECTORY_SEPARATOR . 'model_stats.json';
        if (!is_file($statsFile)) {
            return ['download_count' => 0, 'preference_count' => 0];
        }

        $decoded = json_decode((string) file_get_contents($statsFile), true);
        if (!is_array($decoded) || !is_array($decoded[$id] ?? null)) {
            return ['download_count' => 0, 'preference_count' => 0];
        }

        return [
            'download_count' => (int) ($decoded[$id]['download_count'] ?? 0),
            'preference_count' => (int) ($decoded[$id]['preference_count'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $result */
    private function recognitionLookupText(array $result): string
    {
        return $this->textFromMixed([
            $result['identification'] ?? [],
            $result['part_spec'] ?? [],
            $result['replacement_part_brief'] ?? [],
            $result['object_guess'] ?? [],
        ]);
    }

    private function recordLookupText(array $record): string
    {
        return $this->textFromMixed([
            $record['title'] ?? '',
            $record['query'] ?? '',
            $record['source'] ?? '',
            $record['url'] ?? '',
            $record['criteria_json'] ?? '',
            $record['recognition_summary_json'] ?? '',
        ]);
    }

    /** @return list<string> */
    private function lookupTokens(string $value): array
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $tokens = preg_split('/\s+/', trim($value)) ?: [];
        $stop = [
            'pezzo' => true, 'ricambio' => true, 'modello' => true, 'stampa' => true, 'file' => true,
            'parte' => true, 'part' => true, 'replacement' => true, 'print' => true, 'printing' => true,
            'con' => true, 'per' => true, 'del' => true, 'della' => true, 'dello' => true, 'the' => true,
            'and' => true, 'for' => true, 'with' => true, 'non' => true, 'unknown' => true,
        ];

        $filtered = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (strlen($token) < 3 || isset($stop[$token])) {
                continue;
            }
            $filtered[] = $token;
        }

        return array_values(array_unique($filtered));
    }

    /** @param list<string> $a @param list<string> $b */
    private function hasCodeTokenOverlap(array $a, array $b): bool
    {
        foreach (array_intersect($a, $b) as $token) {
            if (preg_match('/[a-z]+[0-9]+|[0-9]+[a-z]+/i', $token)) {
                return true;
            }
        }
        return false;
    }

    private function textFromMixed(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $text = $this->textFromMixed($item);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
            return trim(implode(' ', $parts));
        }
        return '';
    }
}
