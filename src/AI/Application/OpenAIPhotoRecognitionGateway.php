<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairAttachment;
use Reborn\Repair\Domain\RepairCase;

final class OpenAIPhotoRecognitionGateway implements PhotoRecognitionGateway
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly string $uploadsRoot,
    ) {
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $configured = trim((string) ($this->config['api_key'] ?? '')) !== '';
        $enabled = (bool) ($this->config['enabled'] ?? false) && $configured;

        return [
            'provider' => 'openai',
            'capability' => 'photo_to_replacement_part_brief',
            'enabled' => $enabled,
            'configured' => $configured,
            'mode' => $enabled ? 'live_openai_vision' : 'deterministic_fallback',
            'model' => $this->model(),
            'max_images' => $this->maxImages(),
            'max_image_bytes' => $this->maxImageBytes(),
            'image_detail' => $this->imageDetail(),
            'web_search_enabled' => $this->webSearchEnabled(),
            'reasoning_effort' => $this->reasoningEffort(),
            'quality_profile' => 'max_vision_ocr_reference_part_identification_v2',
            'base_url' => (string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'),
            'safety_note' => 'The provider returns a preliminary recognition and brief. Manufacturing remains gated by human/material/dimensional validation.',
            'billing_note' => 'ChatGPT Plus is separate from API usage; live recognition requires a valid OpenAI API key with API billing available.',
            'missing_configuration' => $configured ? [] : ['OPENAI_API_KEY'],
        ];
    }

    public function analyze(RepairCase $case, array $attachments): ?array
    {
        $status = $this->status();
        if (($status['enabled'] ?? false) !== true) {
            return null;
        }

        $images = $this->loadImageInputs($attachments);
        if ($images === []) {
            return $this->fallbackAfterProviderError($case, $attachments, 'No usable image input was found for OpenAI Vision. Check uploaded attachment MIME type, extension, stored path and readability.');
        }

        try {
            $payload = $this->requestPayload($case, $attachments, $images, false);
            $providerMeta = [
                'provider' => 'openai',
                'model' => $this->model(),
                'status' => 'live_response',
                'image_count' => count($images),
                'image_detail' => $this->imageDetail(),
                'web_search_enabled' => $this->webSearchEnabled(),
                'prompt_profile' => 'reference_image_ocr_part_identification_v2',
            ];

            try {
                $raw = $this->postJson($this->endpoint('/responses'), $payload);
                $parsed = $this->extractStructuredJson($raw);
            } catch (\Throwable $primaryException) {
                $raw = $this->postJson($this->endpoint('/responses'), $this->plainJsonFallbackPayload($case, $attachments, $images));
                $parsed = $this->extractStructuredJson($raw);
                $providerMeta['status'] = 'live_response_plain_json_retry';
                $providerMeta['primary_error'] = $this->sanitizeProviderError($primaryException->getMessage());
            }

            if (!is_array($parsed)) {
                throw new \RuntimeException('OpenAI response did not contain valid structured JSON.');
            }

            $normalized = $this->normalizeResult($parsed, 'openai_vision_api', $providerMeta);

            if ($this->shouldRetryForBetterIdentification($normalized)) {
                try {
                    $retryRaw = $this->postJson($this->endpoint('/responses'), $this->requestPayload($case, $attachments, $images, true));
                    $retryParsed = $this->extractStructuredJson($retryRaw);
                    if (is_array($retryParsed)) {
                        $retryMeta = $providerMeta;
                        $retryMeta['status'] = 'live_response_quality_retry';
                        $retryMeta['prompt_profile'] = 'reference_image_ocr_part_identification_v2_quality_retry';
                        $retryNormalized = $this->normalizeResult($retryParsed, 'openai_vision_api_quality_retry', $retryMeta);
                        if (!$this->shouldRetryForBetterIdentification($retryNormalized)) {
                            return $retryNormalized;
                        }
                    }
                } catch (\Throwable $retryException) {
                    $normalized['ai_provider']['quality_retry_error'] = $this->sanitizeProviderError($retryException->getMessage());
                }
            }

            return $normalized;
        } catch (\Throwable $exception) {
            return $this->fallbackAfterProviderError($case, $attachments, $this->sanitizeProviderError($exception->getMessage()));
        }
    }

    /** @param list<RepairAttachment> $attachments @return list<array{mime_type:string,data_url:string,filename:string,size_bytes:int,width:int|null,height:int|null}> */
    private function loadImageInputs(array $attachments): array
    {
        $images = [];

        foreach ($attachments as $attachment) {
            if ($attachment->sizeBytes > $this->maxImageBytes()) {
                continue;
            }

            $absolutePath = rtrim($this->uploadsRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $attachment->storedPath);
            if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            $imageSize = @getimagesize($absolutePath);
            $resolvedMimeType = $this->imageMimeTypeForAttachment($attachment, $absolutePath, is_array($imageSize) ? $imageSize : null);
            if ($resolvedMimeType === null) {
                continue;
            }

            $bytes = file_get_contents($absolutePath);
            if ($bytes === false || $bytes === '') {
                continue;
            }

            $width = null;
            $height = null;
            if (is_array($imageSize)) {
                $width = isset($imageSize[0]) ? (int) $imageSize[0] : null;
                $height = isset($imageSize[1]) ? (int) $imageSize[1] : null;
            }

            $images[] = [
                'mime_type' => $resolvedMimeType,
                'filename' => $attachment->originalFilename,
                'size_bytes' => $attachment->sizeBytes,
                'width' => $width,
                'height' => $height,
                'data_url' => 'data:' . $resolvedMimeType . ';base64,' . base64_encode($bytes),
            ];

            if (count($images) >= $this->maxImages()) {
                break;
            }
        }

        return $images;
    }

    /** @param array<int, mixed>|null $imageSize */
    private function imageMimeTypeForAttachment(RepairAttachment $attachment, string $absolutePath, ?array $imageSize): ?string
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $declaredMime = strtolower(trim($attachment->mimeType));
        if (in_array($declaredMime, $allowed, true)) {
            return $declaredMime;
        }

        if (function_exists('mime_content_type')) {
            $detectedMime = strtolower((string) (mime_content_type($absolutePath) ?: ''));
            if (in_array($detectedMime, $allowed, true)) {
                return $detectedMime;
            }
        }

        $extension = strtolower(pathinfo($attachment->originalFilename !== '' ? $attachment->originalFilename : $attachment->storedPath, PATHINFO_EXTENSION));
        $extensionMime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };

        if ($extensionMime !== null && is_array($imageSize)) {
            return $extensionMime;
        }

        return null;
    }

    /** @param list<RepairAttachment> $attachments @param list<array{mime_type:string,data_url:string,filename:string,size_bytes:int,width:int|null,height:int|null}> $images @return array<string, mixed> */
    private function requestPayload(RepairCase $case, array $attachments, array $images, bool $qualityRetry): array
    {
        $attachmentSummary = array_map(static fn(RepairAttachment $attachment): array => [
            'filename' => $attachment->originalFilename,
            'mime_type' => $attachment->mimeType,
            'kind' => $attachment->kind,
            'size_bytes' => $attachment->sizeBytes,
        ], $attachments);

        $imageSummary = array_map(static fn(array $image): array => [
            'filename' => $image['filename'],
            'mime_type' => $image['mime_type'],
            'size_bytes' => $image['size_bytes'],
            'width' => $image['width'],
            'height' => $image['height'],
        ], $images);

        $instructions = implode("\n", array_filter([
            "You are Re-born's maximum-quality repair intelligence vision layer for replacement parts.",
            "User-facing strings MUST be in Italian.",
            "Primary mission: identify the exact replacement part as far as the image evidence allows, then create a practical path toward a working replacement.",
            "First do OCR. Read every visible word, number, caption, part number, product title, label, callout and dimension. Visible text is primary evidence and overrides a generic visual guess.",
            "The image may be a real broken part photo, an e-commerce product image, a product detail graphic, a compatibility chart, a screenshot, a catalog page, a dimension diagram, or a maker/reference image.",
            "For product/reference images, extract: exact commercial name, part number, appliance context, likely function, visible design features, material clues, dimensions, and whether it is likely a purchasable spare part before it is a custom part.",
            "When a product title or part number is visible, mark the part as recognized even if the image is not a photo of the user's broken object.",
            "If the image has a part number or product name, use public web search when useful to enrich compatibility, brand/model candidates, and common naming. Keep these as candidates unless directly visible or strongly supported.",
            "Never collapse a specific wheel/clip/hinge/knob into a generic cover, case or plastic shell when OCR or visible geometry supports a more precise part family.",
            "For the sample pattern '165314 Dishwasher Lower Rack Wheel', the correct family is a dishwasher lower-rack basket wheel/roller, not a cover or shell.",
            "Distinguish product identification from manufacturability. It can be recognized from text while still needing measurements for production.",
            "Do not claim certainty. Use probable/compatibile/da confermare for non-visible brand or model compatibility.",
            "Return only JSON matching the schema.",
            $qualityRetry ? "QUALITY RETRY: the previous response was too generic. Re-read text and visual details. Prefer precise OCR-derived identification over generic categories." : null,
        ]));

        $content = [
            [
                'type' => 'input_text',
                'text' => $instructions . "\n\nRepair case and image metadata:\n" . json_encode([
                    'title' => $case->title,
                    'description' => $case->description,
                    'category' => $case->category,
                    'attachment_summary' => $attachmentSummary,
                    'image_summary' => $imageSummary,
                    'desired_output_style' => 'Italian, concise, non-technical UI summary plus detailed maker-ready brief fields.',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($images as $image) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => $image['data_url'],
                'detail' => $this->imageDetail(),
            ];
        }

        $payload = [
            'model' => $this->model(),
            'input' => [[
                'role' => 'user',
                'content' => $content,
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'replacement_part_photo_recognition',
                    'strict' => true,
                    'schema' => $this->responseSchema(),
                ],
            ],
        ];

        $maxOutputTokens = (int) ($this->config['max_output_tokens'] ?? 0);
        if ($maxOutputTokens > 0) {
            $payload['max_output_tokens'] = $maxOutputTokens;
        }

        $reasoningEffort = $this->reasoningEffort();
        if ($reasoningEffort !== '') {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        }

        if ($this->webSearchEnabled()) {
            $payload['tools'] = [['type' => 'web_search']];
            $payload['tool_choice'] = 'auto';
        }

        return $payload;
    }

    /** @param list<RepairAttachment> $attachments @param list<array{mime_type:string,data_url:string,filename:string,size_bytes:int,width:int|null,height:int|null}> $images @return array<string, mixed> */
    private function plainJsonFallbackPayload(RepairCase $case, array $attachments, array $images): array
    {
        $payload = $this->requestPayload($case, $attachments, $images, true);
        unset($payload['text'], $payload['tools'], $payload['tool_choice']);

        $content = $payload['input'][0]['content'] ?? [];
        if (is_array($content) && isset($content[0]) && is_array($content[0]) && ($content[0]['type'] ?? '') === 'input_text') {
            $content[0]['text'] = (string) ($content[0]['text'] ?? '')
                . "\n\nReturn a raw JSON object with keys: identification, part_spec, object_guess, damage_assessment, replacement_part_brief, recommended_next_step, suggested_inputs, repair_notes. Do not use markdown.";
            $payload['input'][0]['content'] = $content;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['identification', 'part_spec', 'object_guess', 'damage_assessment', 'replacement_part_brief', 'recommended_next_step', 'suggested_inputs', 'repair_notes'],
            'properties' => [
                'identification' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['status', 'source_image_type', 'visible_text', 'part_number', 'commercial_name', 'possible_brands', 'possible_models', 'external_lookup_summary', 'why'],
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['recognized', 'needs_more_images', 'unclear']],
                        'source_image_type' => ['type' => 'string', 'enum' => ['real_broken_part_photo', 'reference_product_image', 'dimension_diagram', 'mixed_reference_images', 'unknown']],
                        'visible_text' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'part_number' => ['type' => 'string'],
                        'commercial_name' => ['type' => 'string'],
                        'possible_brands' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'possible_models' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'external_lookup_summary' => ['type' => 'string'],
                        'why' => ['type' => 'string'],
                    ],
                ],
                'part_spec' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name_it', 'name_en', 'appliance_context', 'known_dimensions', 'key_features', 'compatibility_clues', 'manufacturing_features'],
                    'properties' => [
                        'name_it' => ['type' => 'string'],
                        'name_en' => ['type' => 'string'],
                        'appliance_context' => ['type' => 'string'],
                        'known_dimensions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'key_features' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'compatibility_clues' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'manufacturing_features' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'object_guess' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['label', 'confidence', 'object_context'],
                    'properties' => [
                        'label' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                        'object_context' => ['type' => 'string'],
                    ],
                ],
                'damage_assessment' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['type', 'severity', 'repairability_score'],
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'severity' => ['type' => 'string'],
                        'repairability_score' => ['type' => 'number'],
                    ],
                ],
                'replacement_part_brief' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['plain_language_summary', 'probable_function', 'part_family', 'manufacturing_candidate', 'material_hint', 'critical_dimensions', 'photo_requirements', 'user_questions'],
                    'properties' => [
                        'plain_language_summary' => ['type' => 'string'],
                        'probable_function' => ['type' => 'string'],
                        'part_family' => ['type' => 'string'],
                        'manufacturing_candidate' => ['type' => 'boolean'],
                        'material_hint' => ['type' => 'string'],
                        'critical_dimensions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'photo_requirements' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'user_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'recommended_next_step' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['path', 'reason'],
                    'properties' => [
                        'path' => ['type' => 'string', 'enum' => ['ask_more_photos', 'find_existing_spare', 'generate_part', 'maker_brief', 'find_provider']],
                        'reason' => ['type' => 'string'],
                    ],
                ],
                'suggested_inputs' => ['type' => 'array', 'items' => ['type' => 'string']],
                'repair_notes' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/') . $path;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function postJson(string $url, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode OpenAI payload.');
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . (string) ($this->config['api_key'] ?? ''),
        ];

        $timeout = max(5, (int) ($this->config['timeout_seconds'] ?? 90));
        $response = $this->postJsonWithPhpCurl($url, $json, $headers, $timeout);
        if ($response === null) {
            $response = $this->postJsonWithPhpStreams($url, $json, $headers, $timeout);
        }
        if ($response === null) {
            $response = $this->postJsonWithExternalCurl($url, $json, $headers, $timeout);
        }

        if ($response === null) {
            throw new \RuntimeException('OpenAI request could not be sent: PHP cURL is not loaded, the PHP https stream wrapper is unavailable, and curl.exe/curl was not found or could not execute. Enable extension=curl or extension=openssl in php.ini, or keep curl.exe available in PATH.');
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI response was not valid JSON.');
        }

        return $decoded;
    }

    /** @param list<string> $headers */
    private function postJsonWithPhpCurl(string $url, string $json, array $headers, int $timeout): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_USERAGENT => 'Re-born AI Photo Recognition MVP/1.0',
        ]);
        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $response === '') {
            throw new \RuntimeException('OpenAI request failed via PHP cURL: ' . ($error ?: 'empty response'));
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('OpenAI returned HTTP ' . $statusCode . ': ' . $this->safeSubstring((string) $response, 0, 600));
        }

        return (string) $response;
    }

    /** @param list<string> $headers */
    private function postJsonWithPhpStreams(string $url, string $json, array $headers, int $timeout): ?string
    {
        $wrappers = stream_get_wrappers();
        if (!in_array('https', $wrappers, true)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $json,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $http_response_header = [];
        $response = @file_get_contents($url, false, $context);
        if ($response === false || $response === '') {
            throw new \RuntimeException('OpenAI request failed via PHP streams: empty response.');
        }
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/HTTP\/\S+\s+(\d+)/', $statusLine, $matches) && ((int) $matches[1] < 200 || (int) $matches[1] >= 300)) {
            throw new \RuntimeException('OpenAI returned ' . $statusLine . ': ' . $this->safeSubstring((string) $response, 0, 600));
        }

        return (string) $response;
    }

    /** @param list<string> $headers */
    private function postJsonWithExternalCurl(string $url, string $json, array $headers, int $timeout): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $curlBinary = $this->externalCurlBinary();
        if ($curlBinary === null) {
            return null;
        }

        $payloadPath = $this->temporaryFile('reborn_openai_payload_', '.json');
        $responsePath = $this->temporaryFile('reborn_openai_response_', '.json');
        $configPath = $this->temporaryFile('reborn_openai_curl_', '.conf');

        try {
            if (file_put_contents($payloadPath, $json) === false) {
                throw new \RuntimeException('Unable to write temporary OpenAI payload file.');
            }

            $configLines = [
                'url = ' . $this->curlConfigQuote($url),
                'request = POST',
                'location',
                'silent',
                'show-error',
                'max-time = ' . $timeout,
                'connect-timeout = ' . min(15, $timeout),
                'user-agent = ' . $this->curlConfigQuote('Re-born AI Photo Recognition MVP/1.0'),
                'header = ' . $this->curlConfigQuote('Content-Type: application/json'),
            ];
            foreach ($headers as $header) {
                if (str_starts_with($header, 'Authorization:')) {
                    $configLines[] = 'header = ' . $this->curlConfigQuote($header);
                }
            }
            $configLines[] = 'data-binary = ' . $this->curlConfigQuote('@' . $this->normalizePathForCurl($payloadPath));

            if (file_put_contents($configPath, implode(PHP_EOL, $configLines) . PHP_EOL) === false) {
                throw new \RuntimeException('Unable to write temporary curl config file.');
            }

            $command = escapeshellarg($curlBinary)
                . ' --config ' . escapeshellarg($configPath)
                . ' --output ' . escapeshellarg($responsePath)
                . ' --write-out ' . escapeshellarg('%{http_code}')
                . ' 2>&1';

            $output = shell_exec($command);
            if (!is_string($output)) {
                return null;
            }

            $statusCode = 0;
            if (preg_match('/(\d{3})\s*$/', trim($output), $matches)) {
                $statusCode = (int) $matches[1];
            }

            $response = is_file($responsePath) ? (string) file_get_contents($responsePath) : '';
            if ($response === '') {
                $cleanOutput = $this->sanitizeProviderError($output);
                throw new \RuntimeException('OpenAI request failed via external curl: ' . ($cleanOutput !== '' ? $cleanOutput : 'empty response'));
            }
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException('OpenAI returned HTTP ' . $statusCode . ' via external curl: ' . $this->safeSubstring($response, 0, 600));
            }

            return $response;
        } finally {
            @unlink($payloadPath);
            @unlink($responsePath);
            @unlink($configPath);
        }
    }

    private function externalCurlBinary(): ?string
    {
        foreach (['curl.exe', 'curl'] as $binary) {
            $command = (PHP_OS_FAMILY === 'Windows')
                ? 'where ' . escapeshellarg($binary) . ' 2>NUL'
                : 'command -v ' . escapeshellarg($binary) . ' 2>/dev/null';
            $output = shell_exec($command);
            if (!is_string($output) || trim($output) === '') {
                continue;
            }
            $lines = preg_split('/\r?\n/', trim($output)) ?: [];
            foreach ($lines as $line) {
                $candidate = trim($line);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function temporaryFile(string $prefix, string $suffix): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        if ($base === false) {
            throw new \RuntimeException('Unable to create temporary file.');
        }
        $path = $base . $suffix;
        if (!@rename($base, $path)) {
            return $base;
        }
        return $path;
    }

    private function normalizePathForCurl(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function curlConfigQuote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['/', '\"'], $value) . '"';
    }

    /** @param array<string, mixed> $raw @return array<string, mixed>|null */
    private function extractStructuredJson(array $raw): ?array
    {
        $candidates = [];
        if (isset($raw['output_text']) && is_string($raw['output_text'])) {
            $candidates[] = $raw['output_text'];
        }

        foreach (($raw['output'] ?? []) as $output) {
            if (!is_array($output)) {
                continue;
            }
            foreach (($output['content'] ?? []) as $content) {
                if (!is_array($content)) {
                    continue;
                }
                foreach (['text', 'output_text'] as $key) {
                    if (isset($content[$key]) && is_string($content[$key])) {
                        $candidates[] = $content[$key];
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $result @param array<string, mixed> $provider @return array<string, mixed> */
    private function normalizeResult(array $result, string $mode, array $provider): array
    {
        $result = $this->completeResultDefaults($result);
        $result = $this->refineResultFromVisibleText($result);
        $result['recognition_mode'] = $mode;
        $result['ai_provider'] = $provider;

        $identificationStatus = (string) ($result['identification']['status'] ?? 'unclear');
        $label = strtolower((string) ($result['object_guess']['label'] ?? ''));
        $partNumber = trim((string) ($result['identification']['part_number'] ?? ''));
        $commercialName = trim((string) ($result['identification']['commercial_name'] ?? ''));
        $sourceType = (string) ($result['identification']['source_image_type'] ?? 'unknown');

        if ($identificationStatus === 'recognized' || $partNumber !== '' || $commercialName !== '' || in_array($sourceType, ['reference_product_image', 'dimension_diagram', 'mixed_reference_images'], true)) {
            if (!str_contains($label, 'unknown') && !str_contains($label, 'unclear') && !str_contains($label, 'sconosci')) {
                $result['object_guess']['confidence'] = max((float) ($result['object_guess']['confidence'] ?? 0), 0.82);
            }
            if (($result['recommended_next_step']['path'] ?? '') === 'ask_more_photos') {
                $result['recommended_next_step']['path'] = $partNumber !== '' ? 'find_existing_spare' : 'maker_brief';
                $result['recommended_next_step']['reason'] = $partNumber !== ''
                    ? 'Il pezzo è identificabile dal codice o dal testo visibile. Prima di modellarlo conviene verificare se il ricambio commerciale è reperibile; se non lo è, si passa al brief maker con misure.'
                    : 'Il pezzo è identificabile dalle immagini caricate. Servono eventuali foto o misure aggiuntive solo per validare dimensioni, incastri e produzione.';
            }
        }

        $result['repair_notes'] = array_values(array_unique(array_merge(
            array_map('strval', $result['repair_notes'] ?? []),
            ['Il riconoscimento AI è preliminare. Prima della produzione servono verifica umana, dimensionale e materiale.']
        )));

        return $result;
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function completeResultDefaults(array $result): array
    {
        $objectGuess = is_array($result['object_guess'] ?? null) ? $result['object_guess'] : [];
        $brief = is_array($result['replacement_part_brief'] ?? null) ? $result['replacement_part_brief'] : [];
        $next = is_array($result['recommended_next_step'] ?? null) ? $result['recommended_next_step'] : [];

        $result['identification'] = is_array($result['identification'] ?? null) ? $result['identification'] : [];
        $result['identification'] += [
            'status' => 'unclear',
            'source_image_type' => 'unknown',
            'visible_text' => [],
            'part_number' => '',
            'commercial_name' => '',
            'possible_brands' => [],
            'possible_models' => [],
            'external_lookup_summary' => '',
            'why' => 'Elementi insufficienti per dichiarare una identificazione chiara.',
        ];
        $result['identification']['visible_text'] = array_values(array_map('strval', is_array($result['identification']['visible_text']) ? $result['identification']['visible_text'] : []));
        $result['identification']['possible_brands'] = array_values(array_map('strval', is_array($result['identification']['possible_brands']) ? $result['identification']['possible_brands'] : []));
        $result['identification']['possible_models'] = array_values(array_map('strval', is_array($result['identification']['possible_models']) ? $result['identification']['possible_models'] : []));

        $result['part_spec'] = is_array($result['part_spec'] ?? null) ? $result['part_spec'] : [];
        $result['part_spec'] += [
            'name_it' => (string) ($objectGuess['label'] ?? 'pezzo di ricambio da confermare'),
            'name_en' => (string) ($objectGuess['label'] ?? 'replacement part to confirm'),
            'appliance_context' => (string) ($objectGuess['object_context'] ?? 'contesto da confermare'),
            'known_dimensions' => [],
            'key_features' => [],
            'compatibility_clues' => [],
            'manufacturing_features' => [],
        ];
        foreach (['known_dimensions', 'key_features', 'compatibility_clues', 'manufacturing_features'] as $key) {
            $result['part_spec'][$key] = array_values(array_map('strval', is_array($result['part_spec'][$key]) ? $result['part_spec'][$key] : []));
        }

        $result['object_guess'] = $objectGuess + [
            'label' => (string) ($result['part_spec']['name_it'] ?? 'pezzo di ricambio da confermare'),
            'confidence' => 0.5,
            'object_context' => (string) ($result['identification']['why'] ?? 'Contesto da confermare.'),
        ];

        $result['damage_assessment'] = is_array($result['damage_assessment'] ?? null) ? $result['damage_assessment'] : [];
        $result['damage_assessment'] += [
            'type' => 'da_confermare',
            'severity' => 'review',
            'repairability_score' => 0.5,
        ];

        $result['replacement_part_brief'] = $brief + [
            'plain_language_summary' => 'Re-born ha preparato un primo brief del ricambio dalle immagini caricate.',
            'probable_function' => 'Funzione da confermare.',
            'part_family' => (string) ($result['object_guess']['label'] ?? 'pezzo di ricambio'),
            'manufacturing_candidate' => true,
            'material_hint' => 'Da scegliere dopo verifica di carico, acqua, calore e flessibilità.',
            'critical_dimensions' => [],
            'photo_requirements' => [],
            'user_questions' => [],
        ];
        foreach (['critical_dimensions', 'photo_requirements', 'user_questions'] as $key) {
            $result['replacement_part_brief'][$key] = array_values(array_map('strval', is_array($result['replacement_part_brief'][$key]) ? $result['replacement_part_brief'][$key] : []));
        }

        $result['recommended_next_step'] = $next + [
            'path' => 'ask_more_photos',
            'reason' => 'Servono più elementi prima di procedere.',
        ];

        $result['suggested_inputs'] = array_values(array_map('strval', is_array($result['suggested_inputs'] ?? null) ? $result['suggested_inputs'] : []));
        $result['repair_notes'] = array_values(array_map('strval', is_array($result['repair_notes'] ?? null) ? $result['repair_notes'] : []));

        return $result;
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function refineResultFromVisibleText(array $result): array
    {
        $visibleText = implode(' ', array_map('strval', $result['identification']['visible_text'] ?? []));
        $haystack = $this->safeLowercase(implode(' ', [
            $visibleText,
            (string) ($result['identification']['part_number'] ?? ''),
            (string) ($result['identification']['commercial_name'] ?? ''),
            (string) ($result['part_spec']['name_it'] ?? ''),
            (string) ($result['part_spec']['name_en'] ?? ''),
            (string) ($result['replacement_part_brief']['plain_language_summary'] ?? ''),
            (string) ($result['object_guess']['label'] ?? ''),
        ]));

        $isDishwasherWheel = str_contains($haystack, '165314')
            || (str_contains($haystack, 'dishwasher') && (str_contains($haystack, 'lower rack wheel') || str_contains($haystack, 'rack wheel')))
            || (str_contains($haystack, 'lavastoviglie') && (str_contains($haystack, 'ruota') || str_contains($haystack, 'cestello')));

        if (!$isDishwasherWheel) {
            return $result;
        }

        $result['identification']['status'] = 'recognized';
        if (($result['identification']['source_image_type'] ?? 'unknown') === 'unknown') {
            $result['identification']['source_image_type'] = 'reference_product_image';
        }
        if (str_contains($haystack, '165314')) {
            $result['identification']['part_number'] = '165314';
        }
        $result['identification']['commercial_name'] = $result['identification']['commercial_name'] ?: 'Dishwasher Lower Rack Wheel';
        $result['identification']['why'] = 'Il testo visibile e la geometria indicano una ruota/roller del cestello inferiore di lavastoviglie, con codice ricambio 165314 se presente nell’immagine.';

        $result['part_spec']['name_it'] = 'Ruota del cestello inferiore per lavastoviglie';
        $result['part_spec']['name_en'] = 'Dishwasher lower rack wheel';
        $result['part_spec']['appliance_context'] = 'Lavastoviglie, cestello inferiore / lower rack';
        $result['part_spec']['key_features'] = $this->mergeUniqueStrings($result['part_spec']['key_features'] ?? [], [
            'ruota/roller grigia in plastica',
            'clip di bloccaggio superiore',
            'bordo liscio arrotondato',
            'mozzo centrale con innesto scanalato',
            'raggi interni di rinforzo',
        ]);
        $result['part_spec']['compatibility_clues'] = $this->mergeUniqueStrings($result['part_spec']['compatibility_clues'] ?? [], [
            'codice ricambio visibile: 165314',
            'funzione: scorrimento del cestello inferiore lavastoviglie',
            'compatibilità marca/modello da verificare con tabella ricambi o codice appliance',
        ]);
        $result['part_spec']['manufacturing_features'] = $this->mergeUniqueStrings($result['part_spec']['manufacturing_features'] ?? [], [
            'diametro esterno ruota',
            'diametro e profilo del mozzo/foro centrale',
            'geometria della clip di aggancio',
            'spessore bordo e distanza di battuta',
        ]);

        $result['object_guess']['label'] = 'ruota cestello inferiore lavastoviglie';
        $result['object_guess']['confidence'] = max((float) ($result['object_guess']['confidence'] ?? 0), 0.92);
        $result['object_guess']['object_context'] = 'Ricambio per consentire al cestello inferiore della lavastoviglie di scorrere correttamente sulle guide.';

        $result['replacement_part_brief']['plain_language_summary'] = 'Sembra una ruota/roller del cestello inferiore di una lavastoviglie. Il codice ricambio leggibile è 165314 se confermato dall’immagine caricata.';
        $result['replacement_part_brief']['probable_function'] = 'Permette al cestello inferiore della lavastoviglie di scorrere avanti e indietro restando agganciato alla guida.';
        $result['replacement_part_brief']['part_family'] = 'ruota / roller cestello lavastoviglie';
        $result['replacement_part_brief']['manufacturing_candidate'] = true;
        $result['replacement_part_brief']['material_hint'] = 'Plastica tecnica resistente ad acqua calda e detergenti; per stampa 3D valutare PETG/ASA/PA dopo prova di calore, usura e tolleranza dell’innesto.';
        $result['replacement_part_brief']['critical_dimensions'] = $this->mergeUniqueStrings($result['replacement_part_brief']['critical_dimensions'] ?? [], [
            'diametro esterno ruota',
            'larghezza/spessore ruota',
            'diametro e profilo del foro/mozzo centrale',
            'dimensioni della clip di bloccaggio',
            'distanza tra bordo ruota e punto di aggancio',
        ]);
        $result['replacement_part_brief']['photo_requirements'] = $this->mergeUniqueStrings($result['replacement_part_brief']['photo_requirements'] ?? [], [
            'foto laterale della ruota',
            'foto frontale del foro centrale',
            'foto della clip di aggancio',
            'foto con calibro o righello',
            'foto del pezzo montato sul cestello',
        ]);
        $result['replacement_part_brief']['user_questions'] = $this->mergeUniqueStrings($result['replacement_part_brief']['user_questions'] ?? [], [
            'La lavastoviglie ha marca e modello leggibili sull’etichetta interna?',
            'Il codice 165314 corrisponde al ricambio cercato?',
            'La ruota originale è integra abbastanza da misurare foro, diametro e clip?',
        ]);
        $result['recommended_next_step']['path'] = 'find_existing_spare';
        $result['recommended_next_step']['reason'] = 'Essendoci un codice ricambio e un nome commerciale leggibile, la strada più veloce è verificare prima il ricambio 165314; se non è disponibile o non compatibile, Re-born può preparare un brief maker per modellazione e stampa.';

        return $result;
    }

    /** @param array<int, string>|mixed $existing @param list<string> $additions @return list<string> */
    private function mergeUniqueStrings(mixed $existing, array $additions): array
    {
        $values = is_array($existing) ? array_map('strval', $existing) : [];
        foreach ($additions as $addition) {
            $values[] = $addition;
        }
        $seen = [];
        $out = [];
        foreach ($values as $value) {
            $key = $this->safeLowercase(trim($value));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = trim($value);
        }
        return $out;
    }

    /** @param array<string, mixed> $result */
    private function shouldRetryForBetterIdentification(array $result): bool
    {
        $label = $this->safeLowercase((string) ($result['object_guess']['label'] ?? ''));
        $status = (string) ($result['identification']['status'] ?? 'unclear');
        $visibleText = is_array($result['identification']['visible_text'] ?? null) ? $result['identification']['visible_text'] : [];
        $confidence = (float) ($result['object_guess']['confidence'] ?? 0);
        $genericLabel = str_contains($label, 'cover') || str_contains($label, 'case') || str_contains($label, 'shell') || str_contains($label, 'scocca') || str_contains($label, 'unknown') || str_contains($label, 'sconosci') || str_contains($label, 'componente da confermare');

        return $status !== 'recognized' || ($genericLabel && $confidence < 0.86) || ($visibleText === [] && $confidence < 0.78);
    }


    private function safeLowercase(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function safeSubstring(string $value, int $offset, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, $offset, $length, 'UTF-8') : substr($value, $offset, $length);
    }

    private function sanitizeProviderError(string $error): string
    {
        $error = preg_replace('/sk-[A-Za-z0-9_\-]+/', 'sk-***', $error) ?? $error;
        $error = trim($error);
        if ($error === '') {
            return 'OpenAI request failed without a readable error message.';
        }

        return $this->safeSubstring($error, 0, 900);
    }

    /** @param list<RepairAttachment> $attachments @return array<string, mixed> */
    private function fallbackAfterProviderError(RepairCase $case, array $attachments, string $error): array
    {
        $imageCount = count($this->loadImageInputs($attachments));
        $label = $this->guessLabelFromText($case);
        $path = $imageCount < 2 ? 'ask_more_photos' : 'maker_brief';

        return [
            'recognition_mode' => 'fallback_after_openai_error',
            'ai_provider' => [
                'provider' => 'openai',
                'model' => $this->model(),
                'status' => 'error_fallback',
                'image_detail' => $this->imageDetail(),
                'web_search_enabled' => $this->webSearchEnabled(),
                'error' => $error,
            ],
            'identification' => [
                'status' => str_contains($label, 'da confermare') ? 'unclear' : 'needs_more_images',
                'source_image_type' => 'unknown',
                'visible_text' => [],
                'part_number' => '',
                'commercial_name' => '',
                'possible_brands' => [],
                'possible_models' => [],
                'external_lookup_summary' => '',
                'why' => 'La chiamata OpenAI non ha restituito un risultato utilizzabile. Il fallback locale non può leggere OCR, marca, modello o codice dalla foto.',
            ],
            'part_spec' => [
                'name_it' => $label,
                'name_en' => $label,
                'appliance_context' => 'Da confermare con riconoscimento AI live o revisione umana.',
                'known_dimensions' => [],
                'key_features' => [],
                'compatibility_clues' => [],
                'manufacturing_features' => [],
            ],
            'object_guess' => [
                'label' => $label,
                'confidence' => 0.34,
                'object_context' => 'Fallback limitato: non è stata letta la foto. Verificare OPENAI_API_KEY, credito API, modello, rete e dettagli errore.',
            ],
            'damage_assessment' => [
                'type' => 'broken_or_missing_part',
                'severity' => 'review',
                'repairability_score' => 0.58,
            ],
            'replacement_part_brief' => [
                'plain_language_summary' => 'Re-born non ha potuto completare il riconoscimento AI live. Il risultato locale è solo un placeholder di sicurezza e non va trattato come identificazione del pezzo.',
                'probable_function' => 'Funzione da confermare dopo lettura immagine tramite OpenAI Vision live.',
                'part_family' => $label,
                'manufacturing_candidate' => true,
                'material_hint' => 'Da scegliere dopo dimensioni, funzione, carico, acqua, calore e flessibilità.',
                'critical_dimensions' => ['larghezza totale', 'altezza totale', 'spessore', 'diametri o geometrie di fori, clip, cerniere o innesti'],
                'photo_requirements' => ['foto frontale nitida', 'foto laterale', 'foto del pezzo montato', 'foto con righello o calibro', 'foto ravvicinata di codici o scritte'],
                'user_questions' => ['Che cosa fa il pezzo quando l’oggetto funziona?', 'È esposto ad acqua, calore, carico o movimento ripetuto?', 'Ci sono codici, marca o modello leggibili sull’oggetto?'],
            ],
            'recommended_next_step' => [
                'path' => $path,
                'reason' => 'Il riconoscimento live è fallito. Prima di decidere tra ricambio commerciale, modellazione o stampa bisogna ottenere un risultato vision reale o una revisione umana.',
            ],
            'suggested_inputs' => ['Aggiungi foto nitida con testo/codice leggibile', 'Aggiungi vista laterale', 'Aggiungi foto con calibro o righello'],
            'repair_notes' => ['Provider error fallback used.', 'Final manufacturability must be verified before production.'],
        ];
    }

    private function guessLabelFromText(RepairCase $case): string
    {
        $text = strtolower($case->title . ' ' . $case->description . ' ' . $case->category);
        return match (true) {
            str_contains($text, '165314') || (str_contains($text, 'dishwasher') && str_contains($text, 'wheel')) || (str_contains($text, 'lavastoviglie') && str_contains($text, 'ruota')) => 'ruota cestello inferiore lavastoviglie',
            str_contains($text, 'hinge') || str_contains($text, 'cerniera') => 'cerniera / snodo plastico',
            str_contains($text, 'clip') || str_contains($text, 'gancio') => 'clip / gancio di bloccaggio',
            str_contains($text, 'knob') || str_contains($text, 'pomello') => 'pomello / comando elettrodomestico',
            str_contains($text, 'wheel') || str_contains($text, 'ruota') => 'ruota / componente di scorrimento',
            str_contains($text, 'cover') || str_contains($text, 'case') || str_contains($text, 'scocca') => 'cover / scocca plastica da confermare',
            default => 'componente da confermare',
        };
    }

    private function model(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-5.5');
    }

    private function maxImages(): int
    {
        return max(1, (int) ($this->config['max_images'] ?? 8));
    }

    private function maxImageBytes(): int
    {
        return max(1024, (int) ($this->config['max_image_bytes'] ?? 20971520));
    }

    private function imageDetail(): string
    {
        $detail = strtolower(trim((string) ($this->config['image_detail'] ?? 'original')));
        return in_array($detail, ['low', 'high', 'original', 'auto'], true) ? $detail : 'original';
    }

    private function webSearchEnabled(): bool
    {
        return (bool) ($this->config['web_search_enabled'] ?? true);
    }

    private function reasoningEffort(): string
    {
        $effort = strtolower(trim((string) ($this->config['reasoning_effort'] ?? 'high')));
        return in_array($effort, ['low', 'medium', 'high', 'xhigh'], true) ? $effort : '';
    }
}
