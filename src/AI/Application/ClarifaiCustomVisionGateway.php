<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairCase;

final class ClarifaiCustomVisionGateway extends AbstractCloudPhotoRecognitionGateway
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $cfg = $this->providerConfig();
        $enabled = (bool) ($cfg['enabled'] ?? false);
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $userId = trim((string) ($cfg['user_id'] ?? ''));
        $appId = trim((string) ($cfg['app_id'] ?? ''));
        $modelId = trim((string) ($cfg['model_id'] ?? ''));
        $missing = [];
        if ($enabled && $apiKey === '') { $missing[] = 'CLARIFAI_API_KEY'; }
        if ($enabled && $userId === '') { $missing[] = 'CLARIFAI_USER_ID'; }
        if ($enabled && $appId === '') { $missing[] = 'CLARIFAI_APP_ID'; }
        if ($enabled && $modelId === '') { $missing[] = 'CLARIFAI_MODEL_ID'; }

        return [
            'provider' => 'clarifai',
            'capability' => 'specialized_custom_model_part_identification',
            'enabled' => $enabled,
            'configured' => $enabled && $missing === [],
            'mode' => $enabled && $missing === [] ? 'live_clarifai_custom_model' : 'not_configured',
            'model' => $modelId !== '' ? $modelId : 'clarifai-custom-model',
            'model_version' => (string) ($cfg['model_version_id'] ?? ''),
            'base_url' => (string) ($cfg['base_url'] ?? 'https://api.clarifai.com'),
            'quality_profile' => 'trainable_clarifai_specific_part_recognition_v1',
            'training_note' => 'Crea/addestra un visual classifier o detector in Clarifai e inserisci user/app/model/version in .env.',
            'missing_configuration' => $missing,
        ];
    }

    public function analyze(RepairCase $case, array $attachments): ?array
    {
        $cfg = $this->providerConfig();
        if ((bool) ($cfg['enabled'] ?? false) !== true) {
            return null;
        }

        $path = $this->firstImagePath($attachments);
        if ($path === null) {
            return null;
        }

        $status = $this->status();
        if ((bool) ($status['configured'] ?? false) !== true) {
            return null;
        }

        $model = (string) $cfg['model_id'];
        try {
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new \RuntimeException('Unable to read image file for Clarifai request.');
            }

            $url = $this->predictUrl($cfg);
            $payload = [
                'inputs' => [[
                    'data' => [
                        'image' => [
                            'base64' => base64_encode($bytes),
                        ],
                    ],
                ]],
            ];
            $response = $this->postJson($url, $payload, ['Authorization: Key ' . (string) $cfg['api_key']], (int) ($cfg['timeout_seconds'] ?? 45));
            if ($response['status'] >= 400) {
                return $this->errorFallback('fallback_after_clarifai_error', 'clarifai', $model, $attachments, 'Clarifai returned HTTP ' . $response['status'] . ': ' . $this->safeSubstring($response['body'], 0, 800));
            }

            $decoded = $this->decodeJsonResponse($response['body']);
            $statusCode = (int) ($decoded['status']['code'] ?? 0);
            if ($statusCode >= 400000) {
                return $this->errorFallback('fallback_after_clarifai_error', 'clarifai', $model, $attachments, 'Clarifai returned status ' . $statusCode . ': ' . (string) ($decoded['status']['description'] ?? 'unknown error'));
            }

            $predictions = $this->predictionsFromClarifai($decoded);
            if ($predictions === []) {
                return $this->errorFallback('fallback_after_clarifai_no_predictions', 'clarifai', $model, $attachments, 'Clarifai did not return usable concepts/detections.');
            }

            return SpecializedVisionResultFactory::resultFromPredictions($predictions, $attachments, [
                'provider' => 'clarifai',
                'recognition_mode' => 'clarifai_custom_model_api',
                'provider_status' => 'live_response',
                'model' => $model,
                'min_confidence' => (float) ($cfg['min_confidence'] ?? 0.5),
                'possible_brands' => (string) ($cfg['possible_brands'] ?? ''),
                'appliance_context' => (string) ($cfg['appliance_context'] ?? 'Da confermare.'),
                'training_note' => 'Clarifai: addestra un modello visual classifier/detector e usa Predict su quel model id/version.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorFallback('fallback_after_clarifai_error', 'clarifai', $model, $attachments, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function providerConfig(): array
    {
        return is_array($this->config['clarifai'] ?? null) ? $this->config['clarifai'] : [];
    }

    /** @param array<string, mixed> $cfg */
    private function predictUrl(array $cfg): string
    {
        $base = rtrim((string) ($cfg['base_url'] ?? 'https://api.clarifai.com'), '/');
        $userId = rawurlencode((string) $cfg['user_id']);
        $appId = rawurlencode((string) $cfg['app_id']);
        $modelId = rawurlencode((string) $cfg['model_id']);
        $version = trim((string) ($cfg['model_version_id'] ?? ''));
        if ($version !== '') {
            return $base . '/v2/users/' . $userId . '/apps/' . $appId . '/models/' . $modelId . '/versions/' . rawurlencode($version) . '/outputs';
        }

        return $base . '/v2/users/' . $userId . '/apps/' . $appId . '/models/' . $modelId . '/outputs';
    }

    /** @param array<string, mixed> $decoded @return list<array<string, mixed>> */
    private function predictionsFromClarifai(array $decoded): array
    {
        $out = [];
        foreach (($decoded['outputs'] ?? []) as $output) {
            if (!is_array($output)) { continue; }
            foreach (($output['data']['concepts'] ?? []) as $concept) {
                if (!is_array($concept)) { continue; }
                $out[] = [
                    'label' => (string) ($concept['name'] ?? $concept['id'] ?? ''),
                    'confidence' => (float) ($concept['value'] ?? 0.0),
                    'source' => 'clarifai',
                    'raw' => $concept,
                ];
            }
            foreach (($output['data']['regions'] ?? []) as $region) {
                if (!is_array($region)) { continue; }
                foreach (($region['data']['concepts'] ?? []) as $concept) {
                    if (!is_array($concept)) { continue; }
                    $out[] = [
                        'label' => (string) ($concept['name'] ?? $concept['id'] ?? ''),
                        'confidence' => (float) ($concept['value'] ?? 0.0),
                        'source' => 'clarifai',
                        'raw' => ['concept' => $concept, 'region_info' => $region['region_info'] ?? null],
                    ];
                }
            }
        }

        return $out;
    }
}
