<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Infrastructure;

final class MarketplaceWebSearchAdapter
{
    private const USER_AGENT = 'Re-born Marketplace Model Finder/1.0 (+https://reborn.local)';

    public function __construct(private readonly array $config)
    {
    }

    /** @param list<string> $queries @return list<array<string, mixed>> */
    public function search(string $source, array $queries): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $maxResults = max(1, min(10, (int) ($this->config['max_results'] ?? 8)));
        $queryLimit = max(1, min(3, (int) ($this->config['fetch_query_limit'] ?? 1)));
        $queries = array_slice($queries, 0, $queryLimit);
        $results = [];
        foreach ($queries as $query) {
            if (count($results) >= $maxResults) {
                break;
            }

            $html = $this->fetchSearchPage($query);
            if ($html === '') {
                continue;
            }

            foreach ($this->parseDuckDuckGoResults($html) as $candidate) {
                if (count($results) >= $maxResults) {
                    break 2;
                }

                $url = (string) ($candidate['url'] ?? '');
                if ($url === '' || !$this->allowedForSource($source, $url)) {
                    continue;
                }

                $candidate['source'] = $source;
                $candidate['query'] = $query;
                $candidate['score'] = $this->scoreFetchedCandidate($source, (string) $candidate['title'], $url);
                $candidate['download_available'] = preg_match('/\.(stl|3mf|obj|zip)(\?|$)/i', $url) === 1;
                $candidate['file_type'] = $this->fileTypeFromUrl($url);
                $candidate['match_reason'] = $candidate['download_available']
                    ? 'Link web con estensione modello o archivio 3D rilevante.'
                    : 'Risultato web rilevante trovato per la query tecnica del pezzo.';
                $results[$this->fingerprint($url)] = $candidate;
            }
        }

        $values = array_values($results);
        usort($values, static fn(array $a, array $b): int => ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0)));

        return array_slice($values, 0, $maxResults);
    }

    private function enabled(): bool
    {
        return !empty($this->config['fetch_enabled']) && function_exists('curl_init');
    }

    private function fetchSearchPage(string $query): string
    {
        $url = 'https://duckduckgo.com/html/?q=' . rawurlencode($query);
        $timeout = max(3, min(12, (int) ($this->config['timeout_seconds'] ?? 6)));
        $curl = curl_init($url);
        if ($curl === false) {
            return '';
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($response) || $status < 200 || $status >= 400) {
            return '';
        }

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function parseDuckDuckGoResults(string $html): array
    {
        $results = [];
        if (!preg_match_all('/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $href = html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = trim(html_entity_decode(strip_tags((string) $match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = $this->decodeDuckDuckGoUrl($href);
            if ($url === '' || $title === '') {
                continue;
            }

            $results[] = [
                'title' => $title,
                'url' => $url,
                'thumbnail_url' => '',
                'license' => '',
            ];
        }

        return $results;
    }

    private function decodeDuckDuckGoUrl(string $href): string
    {
        if (str_starts_with($href, '//')) {
            $href = 'https:' . $href;
        }

        $parts = parse_url($href);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['uddg'])) {
                $decoded = urldecode((string) $query['uddg']);
                if (filter_var($decoded, FILTER_VALIDATE_URL)) {
                    return $decoded;
                }
            }
        }

        return filter_var($href, FILTER_VALIDATE_URL) ? $href : '';
    }

    private function allowedForSource(string $source, string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return false;
        }

        return match ($source) {
            'thingiverse' => str_contains($host, 'thingiverse.com'),
            'printables' => str_contains($host, 'printables.com'),
            'makerworld' => str_contains($host, 'makerworld.com'),
            'web_stl' => preg_match('/\.(stl|3mf|obj|zip)(\?|$)/i', $url) === 1 || str_contains($url, 'stl'),
            default => true,
        };
    }

    private function scoreFetchedCandidate(string $source, string $title, string $url): int
    {
        $score = match ($source) {
            'thingiverse' => 74,
            'printables' => 72,
            'makerworld' => 70,
            'web_stl' => 68,
            default => 60,
        };

        $text = strtolower($title . ' ' . $url);
        if (preg_match('/\.(stl|3mf|obj)(\?|$)/i', $url) === 1) {
            $score += 14;
        }
        if (str_contains($text, 'replacement') || str_contains($text, 'spare') || str_contains($text, 'ricambio')) {
            $score += 8;
        }
        if (str_contains($text, 'knob') || str_contains($text, 'wheel') || str_contains($text, 'clip') || str_contains($text, 'hinge')) {
            $score += 6;
        }

        return min(95, $score);
    }

    private function fileTypeFromUrl(string $url): string
    {
        if (preg_match('/\.([a-z0-9]{2,4})(\?|$)/i', $url, $match) === 1) {
            $extension = strtolower((string) $match[1]);
            return in_array($extension, ['stl', '3mf', 'obj', 'zip'], true) ? $extension : '';
        }

        return '';
    }

    private function fingerprint(string $value): string
    {
        return sha1(strtolower(trim($value)));
    }
}
