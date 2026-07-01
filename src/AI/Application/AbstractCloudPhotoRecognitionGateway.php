<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairAttachment;

abstract class AbstractCloudPhotoRecognitionGateway implements PhotoRecognitionGateway
{
    public function __construct(
        protected readonly array $config,
        protected readonly string $uploadsRoot,
    ) {
    }

    /** @param list<RepairAttachment> $attachments */
    protected function firstImagePath(array $attachments): ?string
    {
        foreach ($attachments as $attachment) {
            $path = $this->attachmentPath($attachment);
            if ($path !== null && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function attachmentPath(RepairAttachment $attachment): ?string
    {
        $path = rtrim($this->uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $attachment->storedPath);
        return is_file($path) ? $path : null;
    }

    protected function dataUrlForPath(string $path, string $fallbackMime = 'image/jpeg'): string
    {
        $mime = $fallbackMime;
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = @finfo_file($finfo, $path);
                @finfo_close($finfo);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    $mime = $detected;
                }
            }
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException('Unable to read image file for provider request.');
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /** @param array<string, mixed> $payload @param list<string> $headers @return array{status:int, body:string} */
    protected function postJson(string $url, array $payload, array $headers, int $timeout): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode provider JSON payload.');
        }

        $headers = array_values(array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers));
        return $this->postRaw($url, $json, $headers, $timeout);
    }

    /** @param array<string, string|int|float|bool> $payload @param list<string> $headers @return array{status:int, body:string} */
    protected function postForm(string $url, array $payload, array $headers, int $timeout): array
    {
        $body = http_build_query($payload);
        $headers = array_values(array_merge(['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], $headers));
        return $this->postRaw($url, $body, $headers, $timeout);
    }

    /** @param list<string> $headers @return array{status:int, body:string} */
    protected function postRaw(string $url, string $body, array $headers, int $timeout): array
    {
        $timeout = max(10, $timeout);
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout + 30);
        }
        @ini_set('default_socket_timeout', (string) ($timeout + 10));

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl !== false) {
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
                    CURLOPT_USERAGENT => 'Re-born Cloud Free Vision Provider/1.0',
                ]);
                $response = curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $error = curl_error($curl);
                curl_close($curl);

                if ($response !== false) {
                    return ['status' => $status, 'body' => (string) $response];
                }

                throw new \RuntimeException('cURL transport failed: ' . $error);
            }
        }

        $headerString = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerString,
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string) $line, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }

        if ($response !== false) {
            return ['status' => $status, 'body' => (string) $response];
        }

        $curlBinary = $this->externalCurlBinary();
        if ($curlBinary !== null) {
            return $this->postWithExternalCurl($curlBinary, $url, $body, $headers, $timeout);
        }

        throw new \RuntimeException('No HTTP transport available: enable PHP cURL, HTTPS stream wrapper, or curl.exe/curl in PATH.');
    }

    /** @param list<string> $headers @return array{status:int, body:string} */
    private function postWithExternalCurl(string $curlBinary, string $url, string $body, array $headers, int $timeout): array
    {
        $payloadPath = tempnam(sys_get_temp_dir(), 'reborn_provider_payload_');
        if ($payloadPath === false || file_put_contents($payloadPath, $body) === false) {
            throw new \RuntimeException('Unable to write temporary provider payload.');
        }

        try {
            $commandParts = [
                escapeshellarg($curlBinary),
                '-sS',
                '--max-time',
                (string) $timeout,
                '-X',
                'POST',
            ];
            foreach ($headers as $header) {
                $commandParts[] = '-H';
                $commandParts[] = escapeshellarg($header);
            }
            $commandParts[] = '--data-binary';
            $commandParts[] = escapeshellarg('@' . $payloadPath);
            $commandParts[] = '-w';
            $commandParts[] = escapeshellarg("\n__HTTP_STATUS__:%{http_code}");
            $commandParts[] = escapeshellarg($url);

            $output = shell_exec(implode(' ', $commandParts) . ' 2>&1');
            $output = is_string($output) ? $output : '';
            if (!preg_match('/\n__HTTP_STATUS__:(\d{3})\s*$/', $output, $matches)) {
                throw new \RuntimeException('External curl transport failed: ' . $this->safeSubstring($output, 0, 500));
            }

            $status = (int) $matches[1];
            $responseBody = (string) preg_replace('/\n__HTTP_STATUS__:\d{3}\s*$/', '', $output);
            return ['status' => $status, 'body' => $responseBody];
        } finally {
            @unlink($payloadPath);
        }
    }

    private function externalCurlBinary(): ?string
    {
        foreach (['curl.exe', 'curl'] as $binary) {
            $where = stripos(PHP_OS_FAMILY, 'Windows') !== false ? 'where ' : 'command -v ';
            $output = shell_exec($where . escapeshellarg($binary) . ' 2>NUL');
            $path = trim((string) $output);
            if ($path !== '') {
                $firstLine = strtok($path, "\r\n");
                return is_string($firstLine) && $firstLine !== '' ? $firstLine : $binary;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function decodeJsonResponse(string $body): array
    {
        $body = $this->stripBom($body);
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $json = $this->extractFirstJsonObject($body);
        if ($json !== null) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Provider response was not valid JSON: ' . $this->safeSubstring($body, 0, 600));
    }

    protected function extractFirstJsonObject(string $text): ?string
    {
        $text = trim($this->stripBom($text));
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($text);
        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    protected function stripBom(string $text): string
    {
        return preg_replace('/^\xEF\xBB\xBF|^\xFE\xFF|^\xFF\xFE/', '', $text) ?? $text;
    }

    protected function safeSubstring(string $text, int $start, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, $start, $length);
        }

        return substr($text, $start, $length);
    }

    /** @param list<RepairAttachment> $attachments @param list<string> $visibleText @return array<string, mixed> */
    protected function resultFromVisibleText(string $mode, string $provider, string $status, string $model, array $attachments, array $visibleText, string $why = ''): array
    {
        $lines = array_values(array_filter(array_map(static fn(string $line): string => trim($line), $visibleText), static fn(string $line): bool => $line !== ''));
        $text = implode("\n", $lines);
        $partNumber = $this->extractPartNumber($text);
        $commercialName = $this->guessCommercialName($lines, $partNumber);
        $label = $commercialName !== '' ? strtolower($commercialName) : 'componente da immagine';
        $nameIt = $this->translateCommonPartName($commercialName, $text);

        return [
            'identification' => [
                'status' => $lines !== [] ? 'recognized' : 'unclear',
                'source_image_type' => 'user_uploaded_photo_or_reference_image',
                'visible_text' => $lines,
                'part_number' => $partNumber,
                'commercial_name' => $commercialName,
                'possible_brands' => [],
                'possible_models' => [],
                'external_lookup_summary' => '',
                'why' => $why !== '' ? $why : 'Riconoscimento cloud gratuito basato su testo visibile e/o analisi immagine.',
            ],
            'part_spec' => [
                'name_it' => $nameIt,
                'name_en' => $commercialName !== '' ? $commercialName : 'component to confirm',
                'appliance_context' => $this->guessApplianceContext($text),
                'known_dimensions' => [],
                'key_features' => $this->extractFeatureLines($lines),
                'compatibility_clues' => $partNumber !== '' ? ['codice ricambio visibile: ' . $partNumber] : [],
                'manufacturing_features' => ['Geometria e materiale da verificare con misure/foto aggiuntive prima della produzione.'],
            ],
            'object_guess' => [
                'label' => $label,
                'confidence' => $partNumber !== '' ? 0.86 : 0.68,
                'object_context' => 'Riconoscimento preliminare da provider cloud gratuito.',
            ],
            'damage_assessment' => [
                'type' => 'da verificare',
                'severity' => 'review',
                'repairability_score' => 0.65,
            ],
            'replacement_part_brief' => [
                'plain_language_summary' => $partNumber !== ''
                    ? 'Ho letto un possibile codice ricambio: ' . $partNumber . ($commercialName !== '' ? ' — ' . $commercialName . '.' : '.')
                    : 'Ho letto testo utile nell’immagine, ma il codice ricambio va confermato.',
                'probable_function' => 'Funzione da confermare con contesto di montaggio e foto aggiuntive.',
                'part_family' => $this->guessPartFamily($text, $commercialName),
                'manufacturing_candidate' => true,
                'material_hint' => 'Da scegliere dopo verifica: materiale originale, ambiente d’uso, temperatura, carichi e usura.',
                'critical_dimensions' => ['larghezza totale', 'altezza totale', 'spessore', 'diametri/fori/clip se presenti'],
                'photo_requirements' => ['foto frontale nitida', 'foto laterale', 'foto con righello o calibro', 'foto del punto di montaggio'],
                'user_questions' => ['Qual è marca e modello dell’oggetto/elettrodomestico?', 'Puoi misurare diametri, spessori e attacchi?', 'Il pezzo originale è rotto, mancante o usurato?'],
            ],
            'recommended_next_step' => [
                'path' => $partNumber !== '' ? 'find_existing_spare' : 'ask_more_photos',
                'reason' => $partNumber !== '' ? 'Il codice leggibile permette di cercare prima il ricambio commerciale.' : 'Servono più foto e misure per trasformare il riconoscimento in ricambio producibile.',
            ],
            'suggested_inputs' => ['misure principali', 'marca e modello', 'foto del pezzo rotto e del punto di montaggio'],
            'repair_notes' => ['Riconoscimento preliminare da provider cloud gratuito. Prima della produzione servono verifica umana, dimensionale e materiale.'],
            'recognition_mode' => $mode,
            'ai_provider' => [
                'provider' => $provider,
                'status' => $status,
                'model' => $model,
                'image_count' => max(1, count($attachments)),
                'prompt_profile' => 'cloud_free_multi_provider_repair_identification_v1',
            ],
        ];
    }

    /** @param array<string, mixed> $result @param list<RepairAttachment> $attachments @return array<string, mixed> */
    protected function normalizeStructuredResult(array $result, string $mode, string $provider, string $status, string $model, array $attachments): array
    {
        $base = $this->resultFromVisibleText($mode, $provider, $status, $model, $attachments, $this->stringList($result['identification']['visible_text'] ?? []));
        $merged = array_replace_recursive($base, $result);
        $merged['recognition_mode'] = $mode;
        $merged['ai_provider'] = array_replace_recursive($merged['ai_provider'] ?? [], [
            'provider' => $provider,
            'status' => $status,
            'model' => $model,
            'image_count' => max(1, count($attachments)),
            'prompt_profile' => 'cloud_free_multi_provider_repair_identification_v1',
        ]);

        if (!isset($merged['identification']['status']) || (string) $merged['identification']['status'] === '') {
            $merged['identification']['status'] = 'recognized';
        }

        return $merged;
    }

    /** @param list<RepairAttachment> $attachments @return array<string, mixed> */
    protected function errorFallback(string $mode, string $provider, string $model, array $attachments, string $error): array
    {
        $result = $this->resultFromVisibleText($mode, $provider, 'error_fallback', $model, $attachments, [], 'Il provider cloud gratuito non ha restituito un risultato utilizzabile.');
        $result['identification']['status'] = 'unclear';
        $result['replacement_part_brief']['plain_language_summary'] = 'Il provider ' . $provider . ' non ha completato il riconoscimento.';
        $result['ai_provider']['error'] = $this->safeSubstring($error, 0, 900);
        $result['repair_notes'] = [$provider . ' provider error fallback used.'];
        return $result;
    }

    /** @param mixed $value @return list<string> */
    protected function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn(mixed $item): string => trim((string) $item), $value), static fn(string $item): bool => $item !== ''));
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
Sei il modulo Re-born per riconoscere ricambi, componenti rotti o parti sostitutive da immagini.
Rispondi SOLO con JSON valido, senza markdown.
Schema richiesto:
{
  "identification": {"status":"recognized|unclear","source_image_type":"user_photo|reference_product_image|unknown","visible_text":["..."],"part_number":"","commercial_name":"","possible_brands":[],"possible_models":[],"external_lookup_summary":"","why":""},
  "part_spec": {"name_it":"","name_en":"","appliance_context":"","known_dimensions":[],"key_features":[],"compatibility_clues":[],"manufacturing_features":[]},
  "object_guess": {"label":"","confidence":0.0,"object_context":""},
  "damage_assessment": {"type":"","severity":"review","repairability_score":0.0},
  "replacement_part_brief": {"plain_language_summary":"","probable_function":"","part_family":"","manufacturing_candidate":true,"material_hint":"","critical_dimensions":[],"photo_requirements":[],"user_questions":[]},
  "recommended_next_step": {"path":"find_existing_spare|ask_more_photos|maker_brief|human_review","reason":""},
  "suggested_inputs": [],
  "repair_notes": []
}
Priorità: leggi codici, sigle, marca, modello, testo visibile; poi identifica famiglia pezzo e prossima azione più rapida per un utente base.
PROMPT;
    }

    protected function extractPartNumber(string $text): string
    {
        $patterns = [
            '/(?:part\s*(?:number|no\.?|#)|p\/?n|codice|cod\.?|model|modello)\s*[:#-]?\s*([A-Z0-9][A-Z0-9._\/-]{3,})/i',
            '/\b([A-Z]{0,4}\d{4,}[A-Z0-9._\/-]*)\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return strtoupper(trim($matches[1], " .,:;#-\t\n\r\0\x0B"));
            }
        }

        return '';
    }

    /** @param list<string> $lines */
    private function guessCommercialName(array $lines, string $partNumber): string
    {
        foreach ($lines as $line) {
            $clean = trim($line);
            if ($clean === '') {
                continue;
            }
            if ($partNumber !== '') {
                $clean = trim(str_ireplace($partNumber, '', $clean), " -:|#\t\n\r\0\x0B");
            }
            if (preg_match('/part\s*(number|no\.?|#)/i', $clean)) {
                continue;
            }
            if (strlen($clean) >= 4 && preg_match('/[a-zA-Z]/', $clean)) {
                return $clean;
            }
        }

        return '';
    }

    private function translateCommonPartName(string $commercialName, string $text): string
    {
        $haystack = strtolower($commercialName . ' ' . $text);
        if (str_contains($haystack, 'dishwasher') && (str_contains($haystack, 'wheel') || str_contains($haystack, 'roller'))) {
            return 'Ruota del cestello inferiore per lavastoviglie';
        }
        if (str_contains($haystack, 'knob')) { return 'Manopola di ricambio'; }
        if (str_contains($haystack, 'clip')) { return 'Clip di ricambio'; }
        if (str_contains($haystack, 'hinge')) { return 'Cerniera di ricambio'; }
        if (str_contains($haystack, 'cap')) { return 'Tappo/coperchio di ricambio'; }

        return $commercialName !== '' ? 'Ricambio: ' . $commercialName : 'Componente da confermare';
    }

    private function guessApplianceContext(string $text): string
    {
        $haystack = strtolower($text);
        if (str_contains($haystack, 'dishwasher') || str_contains($haystack, 'lavastoviglie')) { return 'Lavastoviglie / dishwasher'; }
        if (str_contains($haystack, 'washing machine') || str_contains($haystack, 'lavatrice')) { return 'Lavatrice / washing machine'; }
        if (str_contains($haystack, 'fridge') || str_contains($haystack, 'refrigerator') || str_contains($haystack, 'frigorifero')) { return 'Frigorifero / refrigerator'; }
        return 'Da confermare.';
    }

    /** @param list<string> $lines @return list<string> */
    private function extractFeatureLines(array $lines): array
    {
        $features = [];
        foreach ($lines as $line) {
            if (preg_match('/(clip|smooth|edge|material|premium|locking|diameter|mm|nylon|abs|pom|tpu|pla|petg|metal|plastic|plastica|foro|vite|gancio|ruota|wheel)/i', $line)) {
                $features[] = $line;
            }
        }
        return array_values(array_unique(array_slice($features, 0, 10)));
    }

    private function guessPartFamily(string $text, string $commercialName): string
    {
        $haystack = strtolower($commercialName . ' ' . $text);
        if (str_contains($haystack, 'wheel') || str_contains($haystack, 'roller') || str_contains($haystack, 'ruota')) { return 'Ruote e rulli'; }
        if (str_contains($haystack, 'clip') || str_contains($haystack, 'gancio')) { return 'Clip, ganci e fissaggi'; }
        if (str_contains($haystack, 'knob') || str_contains($haystack, 'manopola')) { return 'Manopole e comandi'; }
        if (str_contains($haystack, 'hinge') || str_contains($haystack, 'cerniera')) { return 'Cerniere e snodi'; }
        return 'Componente/ricambio da confermare';
    }
}
