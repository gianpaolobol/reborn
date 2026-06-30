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
            'model' => (string) ($this->config['model'] ?? 'gpt-5.4-mini'),
            'max_images' => (int) ($this->config['max_images'] ?? 3),
            'max_image_bytes' => (int) ($this->config['max_image_bytes'] ?? 5242880),
            'base_url' => (string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'),
            'safety_note' => 'The provider returns a preliminary recognition and brief. Manufacturing remains gated by human/material/dimensional validation.',
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
            return null;
        }

        try {
            $payload = $this->requestPayload($case, $attachments, $images);
            $providerMeta = [
                'provider' => 'openai',
                'model' => (string) ($this->config['model'] ?? 'gpt-5.4-mini'),
                'status' => 'live_response',
                'image_count' => count($images),
                'prompt_profile' => 'reference_image_ocr_part_identification_v1',
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

            return $this->normalizeResult($parsed, 'openai_vision_api', $providerMeta);
        } catch (\Throwable $exception) {
            return $this->fallbackAfterProviderError($case, $attachments, $this->sanitizeProviderError($exception->getMessage()));
        }
    }

    /** @param list<RepairAttachment> $attachments @return list<array{mime_type:string,data_url:string,filename:string}> */
    private function loadImageInputs(array $attachments): array
    {
        $maxImages = max(1, (int) ($this->config['max_images'] ?? 3));
        $maxBytes = max(1024, (int) ($this->config['max_image_bytes'] ?? 5242880));
        $images = [];

        foreach ($attachments as $attachment) {
            if (!str_starts_with($attachment->mimeType, 'image/')) {
                continue;
            }

            if (!in_array($attachment->mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }

            if ($attachment->sizeBytes > $maxBytes) {
                continue;
            }

            $absolutePath = rtrim($this->uploadsRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $attachment->storedPath);
            if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            $bytes = file_get_contents($absolutePath);
            if ($bytes === false || $bytes === '') {
                continue;
            }

            $images[] = [
                'mime_type' => $attachment->mimeType,
                'filename' => $attachment->originalFilename,
                'data_url' => 'data:' . $attachment->mimeType . ';base64,' . base64_encode($bytes),
            ];

            if (count($images) >= $maxImages) {
                break;
            }
        }

        return $images;
    }

    /** @param list<RepairAttachment> $attachments @param list<array{mime_type:string,data_url:string,filename:string}> $images @return array<string, mixed> */
    private function requestPayload(RepairCase $case, array $attachments, array $images): array
    {
        $attachmentSummary = array_map(static fn(RepairAttachment $attachment): array => [
            'filename' => $attachment->originalFilename,
            'mime_type' => $attachment->mimeType,
            'kind' => $attachment->kind,
            'size_bytes' => $attachment->sizeBytes,
        ], $attachments);

        $instructions = implode("\n", [
            "You are Re-born's repair intelligence photo recognition layer for replacement parts.",
            "User-facing strings MUST be in Italian.",
            "Analyze every uploaded image. Images may be real photos of a broken part, a product listing, a product detail graphic, a dimensions diagram, or a reference image found online.",
            "If an image contains readable text, product name, part number, dimensions, labels, captions, or callouts, read them and use them as primary evidence.",
            "If a reference/product image clearly names the part, treat the part as recognized even if it is not a real photo of the user's broken object.",
            "For e-commerce/reference images, extract part number, commercial part name, dimensions, appliance context, visible features and manufacturing clues.",
            "For a replacement-part generation flow, distinguish these outcomes: recognized part, needs more images, or unclear image.",
            "Do not ask for more images when the part can be identified from visible text or from a dimension diagram. Ask only for additional images needed for manufacturing or fit validation.",
            "Do not claim certainty. Manufacturing remains gated by human, dimensional and material validation.",
            "Return only JSON matching the schema."
        ]);

        $content = [
            [
                'type' => 'input_text',
                'text' => $instructions . "\n\nRepair case:\n" . json_encode([
                    'title' => $case->title,
                    'description' => $case->description,
                    'category' => $case->category,
                    'attachment_summary' => $attachmentSummary,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($images as $image) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => $image['data_url'],
            ];
        }

        return [
            'model' => (string) ($this->config['model'] ?? 'gpt-5.4-mini'),
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
    }

    /** @param list<RepairAttachment> $attachments @param list<array{mime_type:string,data_url:string,filename:string}> $images @return array<string, mixed> */
    private function plainJsonFallbackPayload(RepairCase $case, array $attachments, array $images): array
    {
        $payload = $this->requestPayload($case, $attachments, $images);
        unset($payload['text']);

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
                    'required' => ['status', 'source_image_type', 'visible_text', 'part_number', 'why'],
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['recognized', 'needs_more_images', 'unclear']],
                        'source_image_type' => ['type' => 'string', 'enum' => ['real_broken_part_photo', 'reference_product_image', 'dimension_diagram', 'mixed_reference_images', 'unknown']],
                        'visible_text' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'part_number' => ['type' => 'string'],
                        'why' => ['type' => 'string'],
                    ],
                ],
                'part_spec' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name_it', 'name_en', 'appliance_context', 'known_dimensions', 'key_features'],
                    'properties' => [
                        'name_it' => ['type' => 'string'],
                        'name_en' => ['type' => 'string'],
                        'appliance_context' => ['type' => 'string'],
                        'known_dimensions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'key_features' => ['type' => 'array', 'items' => ['type' => 'string']],
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

        $timeout = max(5, (int) ($this->config['timeout_seconds'] ?? 30));
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                throw new \RuntimeException('Unable to initialize cURL.');
            }
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_USERAGENT => 'Re-born AI Photo Recognition MVP/1.0',
            ]);
            $response = curl_exec($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($response === false || $response === '') {
                throw new \RuntimeException('OpenAI request failed: ' . ($error ?: 'empty response'));
            }
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException('OpenAI returned HTTP ' . $statusCode . ': ' . substr((string) $response, 0, 600));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $json,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);
            $response = file_get_contents($url, false, $context);
            if ($response === false || $response === '') {
                throw new \RuntimeException('OpenAI request failed: empty response.');
            }
            $statusLine = $http_response_header[0] ?? '';
            if (preg_match('/HTTP\/\S+\s+(\d+)/', $statusLine, $matches) && ((int) $matches[1] < 200 || (int) $matches[1] >= 300)) {
                throw new \RuntimeException('OpenAI returned ' . $statusLine . ': ' . substr((string) $response, 0, 600));
            }
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI response was not valid JSON.');
        }

        return $decoded;
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
        $result['recognition_mode'] = $mode;
        $result['ai_provider'] = $provider;

        $identificationStatus = (string) ($result['identification']['status'] ?? 'unclear');
        $label = strtolower((string) ($result['object_guess']['label'] ?? ''));
        $partNumber = trim((string) ($result['identification']['part_number'] ?? ''));
        $sourceType = (string) ($result['identification']['source_image_type'] ?? 'unknown');

        if ($identificationStatus === 'recognized' || $partNumber !== '' || in_array($sourceType, ['reference_product_image', 'dimension_diagram', 'mixed_reference_images'], true)) {
            if (!str_contains($label, 'unknown') && !str_contains($label, 'unclear') && !str_contains($label, 'sconosci')) {
                $result['object_guess']['confidence'] = max((float) ($result['object_guess']['confidence'] ?? 0), 0.78);
            }
            if (($result['recommended_next_step']['path'] ?? '') === 'ask_more_photos') {
                $result['recommended_next_step']['path'] = 'maker_brief';
                $result['recommended_next_step']['reason'] = 'Il pezzo è identificabile dalle immagini caricate. Servono eventuali foto o misure aggiuntive solo per validare dimensioni, incastri e produzione.';
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
            'why' => 'Elementi insufficienti per dichiarare una identificazione chiara.',
        ];

        $result['part_spec'] = is_array($result['part_spec'] ?? null) ? $result['part_spec'] : [];
        $result['part_spec'] += [
            'name_it' => (string) ($objectGuess['label'] ?? 'pezzo di ricambio da confermare'),
            'name_en' => (string) ($objectGuess['label'] ?? 'replacement part to confirm'),
            'appliance_context' => (string) ($objectGuess['object_context'] ?? 'contesto da confermare'),
            'known_dimensions' => [],
            'key_features' => [],
        ];

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

        $result['recommended_next_step'] = $next + [
            'path' => 'ask_more_photos',
            'reason' => 'Servono più elementi prima di procedere.',
        ];

        $result['suggested_inputs'] = array_values(array_map('strval', is_array($result['suggested_inputs'] ?? null) ? $result['suggested_inputs'] : []));
        $result['repair_notes'] = array_values(array_map('strval', is_array($result['repair_notes'] ?? null) ? $result['repair_notes'] : []));

        return $result;
    }

    private function sanitizeProviderError(string $error): string
    {
        $error = preg_replace('/sk-[A-Za-z0-9_\-]+/', 'sk-***', $error) ?? $error;
        $error = trim($error);
        if ($error === '') {
            return 'OpenAI request failed without a readable error message.';
        }

        return mb_substr($error, 0, 900);
    }

    /** @param list<RepairAttachment> $attachments @return array<string, mixed> */
    private function fallbackAfterProviderError(RepairCase $case, array $attachments, string $error): array
    {
        $imageCount = count(array_filter($attachments, static fn(RepairAttachment $attachment): bool => str_starts_with($attachment->mimeType, 'image/')));
        $label = $this->guessLabelFromText($case);
        $path = $imageCount < 2 ? 'ask_more_photos' : 'generate_part';

        return [
            'recognition_mode' => 'fallback_after_openai_error',
            'ai_provider' => [
                'provider' => 'openai',
                'model' => (string) ($this->config['model'] ?? 'gpt-5.4-mini'),
                'status' => 'error_fallback',
                'error' => $error,
            ],
            'identification' => [
                'status' => 'unclear',
                'source_image_type' => 'unknown',
                'visible_text' => [],
                'part_number' => '',
                'why' => 'La chiamata OpenAI non ha restituito un risultato utilizzabile. Il fallback locale non può leggere il contenuto della foto.',
            ],
            'part_spec' => [
                'name_it' => $label,
                'name_en' => $label,
                'appliance_context' => 'Da confermare con riconoscimento AI live o revisione umana.',
                'known_dimensions' => [],
                'key_features' => [],
            ],
            'object_guess' => [
                'label' => $label,
                'confidence' => 0.48,
                'object_context' => 'Fallback inferred from intake text and attachment metadata after provider error.',
            ],
            'damage_assessment' => [
                'type' => 'broken_or_missing_part',
                'severity' => 'review',
                'repairability_score' => 0.58,
            ],
            'replacement_part_brief' => [
                'plain_language_summary' => 'Re-born could not complete live AI recognition, so it prepared a safe preliminary brief from the repair request.',
                'probable_function' => 'Unknown until the uploaded photos are reviewed.',
                'part_family' => $label,
                'manufacturing_candidate' => true,
                'material_hint' => 'To be selected after dimensions, load, heat and flexibility are known.',
                'critical_dimensions' => ['overall width', 'overall height', 'thickness', 'hole/clip/hinge diameters if present'],
                'photo_requirements' => ['close-up of broken part', 'side view', 'photo of full object', 'photo with ruler or coin for scale'],
                'user_questions' => ['What does the part hold, guide, block or move?', 'Is the part exposed to heat, water, load or repeated bending?'],
            ],
            'recommended_next_step' => [
                'path' => $path,
                'reason' => 'Live AI recognition failed, so Re-born asks for enough evidence to produce a maker-ready replacement brief safely.',
            ],
            'suggested_inputs' => ['Add a side photo', 'Add one photo with a ruler', 'Describe what the part does when the object works'],
            'repair_notes' => ['Provider error fallback used.', 'Final manufacturability must be verified before production.'],
        ];
    }

    private function guessLabelFromText(RepairCase $case): string
    {
        $text = strtolower($case->title . ' ' . $case->description . ' ' . $case->category);
        return match (true) {
            str_contains($text, 'hinge') || str_contains($text, 'cerniera') => 'hinge / pivot component',
            str_contains($text, 'clip') || str_contains($text, 'gancio') => 'clip / retaining hook',
            str_contains($text, 'knob') || str_contains($text, 'pomello') => 'knob / control interface',
            str_contains($text, 'wheel') || str_contains($text, 'ruota') => 'wheel / rolling component',
            str_contains($text, 'cover') || str_contains($text, 'case') || str_contains($text, 'scocca') => 'plastic cover / shell',
            default => 'unknown replacement part',
        };
    }
}
