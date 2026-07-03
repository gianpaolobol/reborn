<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairCase;

final class RoboflowHostedVisionGateway extends AbstractCloudPhotoRecognitionGateway
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $cfg = $this->providerConfig();
        $enabled = (bool) ($cfg['enabled'] ?? false);
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $mode = $this->roboflowMode($cfg);
        $missing = [];

        if ($enabled && $apiKey === '') { $missing[] = 'ROBOFLOW_API_KEY'; }

        if ($mode === 'workflow') {
            if ($enabled && trim((string) ($cfg['workspace'] ?? '')) === '') { $missing[] = 'ROBOFLOW_WORKSPACE'; }
            if ($enabled && trim((string) ($cfg['workflow_id'] ?? '')) === '') { $missing[] = 'ROBOFLOW_WORKFLOW_ID'; }
        } else {
            if ($enabled && trim((string) ($cfg['model_id'] ?? '')) === '') { $missing[] = 'ROBOFLOW_MODEL_ID'; }
        }

        $configured = $enabled && $missing === [];
        $model = $mode === 'workflow'
            ? trim((string) ($cfg['workspace'] ?? '')) . '/workflows/' . trim((string) ($cfg['workflow_id'] ?? ''))
            : trim((string) ($cfg['model_id'] ?? ''));

        return [
            'provider' => 'roboflow',
            'capability' => $mode === 'workflow'
                ? 'specialized_roboflow_workflow_clip_part_detection'
                : 'specialized_roboflow_model_part_detection',
            'enabled' => $enabled,
            'configured' => $configured,
            'mode' => $configured
                ? ($mode === 'workflow' ? 'live_roboflow_serverless_workflow' : 'live_roboflow_hosted_inference')
                : 'not_configured',
            'roboflow_mode' => $mode,
            'model' => $model !== '/workflows/' && $model !== '' ? $model : ($mode === 'workflow' ? 'workspace/workflows/workflow-id' : 'roboflow-project/version'),
            'base_url' => $mode === 'workflow'
                ? (string) ($cfg['serverless_url'] ?? 'https://serverless.roboflow.com')
                : (string) ($cfg['base_url'] ?? 'https://detect.roboflow.com'),
            'quality_profile' => $mode === 'workflow'
                ? 'trainable_or_zero_shot_roboflow_workflow_specific_part_detection_v1'
                : 'trainable_roboflow_specific_part_detection_v1',
            'training_note' => $mode === 'workflow'
                ? 'Usa un Roboflow Workflow Serverless, ad esempio CLIP, con classi configurate in ROBOFLOW_WORKFLOW_CLASSES.'
                : 'Addestra un progetto Roboflow e inserisci model id/versione per usare Hosted Inference.',
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

        $mode = $this->roboflowMode($cfg);
        return $mode === 'workflow'
            ? $this->analyzeWithWorkflow($path, $attachments, $cfg)
            : $this->analyzeWithHostedModel($path, $attachments, $cfg);
    }

    /** @param list<mixed> $attachments @param array<string, mixed> $cfg @return array<string, mixed> */
    private function analyzeWithHostedModel(string $path, array $attachments, array $cfg): array
    {
        $model = (string) $cfg['model_id'];
        try {
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new \RuntimeException('Unable to read image file for Roboflow request.');
            }

            $url = $this->inferenceUrl($cfg);
            $payloadMode = strtolower(trim((string) ($cfg['payload_mode'] ?? 'base64_form')));
            if ($payloadMode === 'raw_image') {
                $body = $bytes;
                $headers = ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];
            } else {
                $body = 'image=' . rawurlencode(base64_encode($bytes));
                $headers = ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];
            }

            $response = $this->postRaw($url, $body, $headers, (int) ($cfg['timeout_seconds'] ?? 45));
            if ($response['status'] >= 400) {
                return $this->errorFallback('fallback_after_roboflow_error', 'roboflow', $model, $attachments, 'Roboflow returned HTTP ' . $response['status'] . ': ' . $this->safeSubstring($response['body'], 0, 800));
            }

            $decoded = $this->decodeJsonResponse($response['body']);
            $predictions = $this->predictionsFromRoboflow($decoded);
            if ($predictions === []) {
                return $this->errorFallback('fallback_after_roboflow_no_predictions', 'roboflow', $model, $attachments, 'Roboflow did not return usable predictions.');
            }

            return SpecializedVisionResultFactory::resultFromPredictions($predictions, $attachments, [
                'provider' => 'roboflow',
                'recognition_mode' => 'roboflow_hosted_inference_api',
                'provider_status' => 'live_response',
                'model' => $model,
                'min_confidence' => (float) ($cfg['min_confidence'] ?? 0.5),
                'possible_brands' => (string) ($cfg['possible_brands'] ?? ''),
                'appliance_context' => (string) ($cfg['appliance_context'] ?? 'Da confermare.'),
                'training_note' => 'Roboflow: addestra dataset e deploy/versione; Re-born chiama Hosted Inference con model id/version.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorFallback('fallback_after_roboflow_error', 'roboflow', $model, $attachments, $e->getMessage());
        }
    }

    /** @param list<mixed> $attachments @param array<string, mixed> $cfg @return array<string, mixed> */
    private function analyzeWithWorkflow(string $path, array $attachments, array $cfg): array
    {
        $model = trim((string) ($cfg['workspace'] ?? '')) . '/workflows/' . trim((string) ($cfg['workflow_id'] ?? ''));
        try {
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new \RuntimeException('Unable to read image file for Roboflow workflow request.');
            }

            $payload = [
                'api_key' => (string) $cfg['api_key'],
                'inputs' => [
                    'image' => [
                        'type' => 'base64',
                        'value' => base64_encode($bytes),
                    ],
                    'classes' => $this->workflowClasses($cfg),
                ],
            ];

            $response = $this->postJson($this->workflowUrl($cfg), $payload, [], (int) ($cfg['timeout_seconds'] ?? 45));
            if ($response['status'] >= 400) {
                return $this->errorFallback('fallback_after_roboflow_workflow_error', 'roboflow', $model, $attachments, 'Roboflow workflow returned HTTP ' . $response['status'] . ': ' . $this->safeSubstring($response['body'], 0, 800));
            }

            $decoded = $this->decodeJsonResponse($response['body']);
            $predictions = $this->predictionsFromRoboflow($decoded);
            if ($predictions === []) {
                return $this->errorFallback('fallback_after_roboflow_workflow_no_predictions', 'roboflow', $model, $attachments, 'Roboflow workflow did not return usable predictions. Response: ' . $this->safeSubstring($response['body'], 0, 800));
            }

            return SpecializedVisionResultFactory::resultFromPredictions($predictions, $attachments, [
                'provider' => 'roboflow',
                'recognition_mode' => 'roboflow_workflow_clip_api',
                'provider_status' => 'live_response',
                'model' => $model,
                'min_confidence' => (float) ($cfg['min_confidence'] ?? 0.5),
                'possible_brands' => (string) ($cfg['possible_brands'] ?? ''),
                'appliance_context' => (string) ($cfg['appliance_context'] ?? 'Da confermare.'),
                'training_note' => 'Roboflow Workflow Serverless/CLIP: aggiorna ROBOFLOW_WORKFLOW_CLASSES per confrontare classi specifiche di Re-born.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorFallback('fallback_after_roboflow_workflow_error', 'roboflow', $model, $attachments, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function providerConfig(): array
    {
        return is_array($this->config['roboflow'] ?? null) ? $this->config['roboflow'] : [];
    }

    /** @param array<string, mixed> $cfg */
    private function roboflowMode(array $cfg): string
    {
        $mode = strtolower(trim((string) ($cfg['mode'] ?? '')));
        if ($mode === 'workflow' || $mode === 'hosted_model') {
            return $mode;
        }

        $workspace = trim((string) ($cfg['workspace'] ?? ''));
        $workflowId = trim((string) ($cfg['workflow_id'] ?? ''));
        return $workspace !== '' && $workflowId !== '' ? 'workflow' : 'hosted_model';
    }

    /** @param array<string, mixed> $cfg */
    private function inferenceUrl(array $cfg): string
    {
        $base = rtrim((string) ($cfg['base_url'] ?? 'https://detect.roboflow.com'), '/');
        $modelId = implode('/', array_map(static fn(string $part): string => rawurlencode($part), explode('/', trim((string) $cfg['model_id'], '/'))));
        $params = [
            'api_key' => (string) $cfg['api_key'],
            'confidence' => (string) (int) ($cfg['confidence'] ?? 40),
            'overlap' => (string) (int) ($cfg['overlap'] ?? 30),
        ];

        return $base . '/' . $modelId . '?' . http_build_query($params);
    }

    /** @param array<string, mixed> $cfg */
    private function workflowUrl(array $cfg): string
    {
        $base = rtrim((string) ($cfg['serverless_url'] ?? 'https://serverless.roboflow.com'), '/');
        $workspace = rawurlencode(trim((string) ($cfg['workspace'] ?? '')));
        $workflowId = rawurlencode(trim((string) ($cfg['workflow_id'] ?? '')));
        return $base . '/' . $workspace . '/workflows/' . $workflowId;
    }

    /** @param array<string, mixed> $cfg */
    private function workflowClasses(array $cfg): string
    {
        $classes = trim((string) ($cfg['workflow_classes'] ?? ''));
        if ($classes !== '') {
            return $classes;
        }

        return 'strap buckle, ladder lock buckle, bicycle bottom bracket support, washing machine knob, plastic clip, hinge, bracket, connector, handle, screw, knob, unknown part';
    }

    /** @param array<string, mixed> $decoded @return list<array<string, mixed>> */
    private function predictionsFromRoboflow(array $decoded): array
    {
        $out = [];
        $this->collectPredictionsRecursive($decoded, $out);

        $dedup = [];
        foreach ($out as $prediction) {
            $label = trim((string) ($prediction['label'] ?? ''));
            if ($label === '') { continue; }
            $key = strtolower($label);
            $confidence = (float) ($prediction['confidence'] ?? 0.0);
            if (!isset($dedup[$key]) || (float) $dedup[$key]['confidence'] < $confidence) {
                $dedup[$key] = $prediction;
            }
        }

        return array_values($dedup);
    }

    /** @param mixed $node @param list<array<string, mixed>> $out */
    private function collectPredictionsRecursive(mixed $node, array &$out): void
    {
        if (!is_array($node)) {
            return;
        }

        if ($this->looksLikePrediction($node)) {
            $label = trim((string) ($node['class'] ?? $node['class_name'] ?? $node['label'] ?? $node['name'] ?? $node['top'] ?? $node['top_class'] ?? $node['category'] ?? ''));
            $confidence = $node['confidence'] ?? $node['score'] ?? $node['value'] ?? $node['similarity'] ?? $node['probability'] ?? 0.0;
            $out[] = [
                'label' => $label,
                'confidence' => is_numeric($confidence) ? (float) $confidence : 0.0,
                'source' => 'roboflow',
                'raw' => $node,
            ];
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if (is_string($key) && $this->isPotentialClassScoreMap($value)) {
                    foreach ($value as $className => $score) {
                        if (is_string($className) && is_numeric($score)) {
                            $out[] = [
                                'label' => $className,
                                'confidence' => (float) $score,
                                'source' => 'roboflow',
                                'raw' => [$className => $score],
                            ];
                        }
                    }
                }
                $this->collectPredictionsRecursive($value, $out);
            }
        }
    }

    /** @param array<mixed> $node */
    private function looksLikePrediction(array $node): bool
    {
        $hasLabel = isset($node['class']) || isset($node['class_name']) || isset($node['label']) || isset($node['name']) || isset($node['top']) || isset($node['top_class']) || isset($node['category']);
        $hasScore = isset($node['confidence']) || isset($node['score']) || isset($node['value']) || isset($node['similarity']) || isset($node['probability']);
        return $hasLabel && $hasScore;
    }

    /** @param array<mixed> $node */
    private function isPotentialClassScoreMap(array $node): bool
    {
        if ($node === []) { return false; }
        $count = 0;
        foreach ($node as $key => $value) {
            if (!is_string($key) || !is_numeric($value)) {
                return false;
            }
            if (strlen($key) > 80) {
                return false;
            }
            $count++;
        }

        return $count > 0 && $count <= 50;
    }
}
