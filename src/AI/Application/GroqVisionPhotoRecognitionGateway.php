<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairCase;

final class GroqVisionPhotoRecognitionGateway extends AbstractCloudPhotoRecognitionGateway
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $cfg = $this->providerConfig();
        $enabled = (bool) ($cfg['enabled'] ?? true);
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));

        return [
            'provider' => 'groq',
            'capability' => 'cloud_vision_repair_part_identification',
            'enabled' => $enabled,
            'configured' => $enabled && $apiKey !== '',
            'mode' => $enabled && $apiKey !== '' ? 'live_groq_vision' : 'not_configured',
            'model' => (string) ($cfg['model'] ?? 'meta-llama/llama-4-scout-17b-16e-instruct'),
            'base_url' => (string) ($cfg['base_url'] ?? 'https://api.groq.com/openai/v1'),
            'quality_profile' => 'cloud_free_groq_vision_repair_identification_v1',
            'billing_note' => 'Groq free/developer usage is provider-rate-limited; use as cloud vision after OCR.',
            'missing_configuration' => $enabled && $apiKey === '' ? ['GROQ_API_KEY'] : [],
        ];
    }

    public function analyze(RepairCase $case, array $attachments): ?array
    {
        $cfg = $this->providerConfig();
        if ((bool) ($cfg['enabled'] ?? true) !== true || trim((string) ($cfg['api_key'] ?? '')) === '') {
            return null;
        }

        $path = $this->firstImagePath($attachments);
        if ($path === null) {
            return null;
        }

        $model = (string) ($cfg['model'] ?? 'meta-llama/llama-4-scout-17b-16e-instruct');
        try {
            $baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://api.groq.com/openai/v1'), '/');
            $response = $this->postJson($baseUrl . '/chat/completions', [
                'model' => $model,
                'temperature' => (float) ($cfg['temperature'] ?? 0.1),
                'max_tokens' => (int) ($cfg['max_output_tokens'] ?? 2500),
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->prompt()],
                        ['type' => 'image_url', 'image_url' => ['url' => $this->dataUrlForPath($path)]],
                    ],
                ]],
            ], ['Authorization: Bearer ' . (string) $cfg['api_key']], (int) ($cfg['timeout_seconds'] ?? 90));

            if ($response['status'] >= 400) {
                return $this->errorFallback('fallback_after_groq_error', 'groq', $model, $attachments, 'Groq returned HTTP ' . $response['status'] . ': ' . $this->safeSubstring($response['body'], 0, 700));
            }

            $json = $this->decodeJsonResponse($response['body']);
            $content = (string) ($json['choices'][0]['message']['content'] ?? '');
            $object = $this->extractFirstJsonObject($content);
            if ($object === null) {
                return $this->errorFallback('fallback_after_groq_error', 'groq', $model, $attachments, 'Groq response did not include a structured JSON object: ' . $this->safeSubstring($content, 0, 700));
            }

            $result = json_decode($object, true);
            if (!is_array($result)) {
                return $this->errorFallback('fallback_after_groq_error', 'groq', $model, $attachments, 'Groq structured JSON could not be decoded.');
            }

            return $this->normalizeStructuredResult($result, 'groq_vision_api', 'groq', 'live_response', $model, $attachments);
        } catch (\Throwable $e) {
            return $this->errorFallback('fallback_after_groq_error', 'groq', $model, $attachments, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function providerConfig(): array
    {
        return is_array($this->config['groq'] ?? null) ? $this->config['groq'] : [];
    }
}
