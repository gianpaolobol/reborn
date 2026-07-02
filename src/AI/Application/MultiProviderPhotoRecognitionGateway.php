<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairAttachment;
use Reborn\Repair\Domain\RepairCase;

final class MultiProviderPhotoRecognitionGateway implements PhotoRecognitionGateway
{
    /** @param array<string, mixed> $config @param array<string, PhotoRecognitionGateway> $providers */
    public function __construct(
        private readonly array $config,
        private readonly array $providers,
    ) {
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $provider = $this->providerName();
        $providerStatuses = [];
        foreach ($this->providers as $name => $gateway) {
            $providerStatuses[$name] = $gateway->status();
        }

        $selectedProviderNames = $provider === 'auto' ? $this->providerOrder() : [$provider];
        $missing = [];
        $configuredSelectedProvider = false;
        foreach ($providerStatuses as $name => $status) {
            if (!in_array($name, $selectedProviderNames, true)) {
                continue;
            }
            $configuredSelectedProvider = $configuredSelectedProvider || (bool) ($status['configured'] ?? false);
            foreach (($status['missing_configuration'] ?? []) as $item) {
                $missing[] = $name . ':' . (string) $item;
            }
        }

        $qualityGate = $this->qualityGate();

        return [
            'provider' => $provider,
            'capability' => 'photo_to_replacement_part_brief',
            'enabled' => (bool) ($this->config['enabled'] ?? true),
            'configured' => $configuredSelectedProvider,
            'mode' => $provider === 'auto' ? 'auto_provider_router' : 'single_provider_router',
            'provider_order' => $this->providerOrder(),
            'quality_profile' => 'max_vision_ocr_reference_part_identification_v2',
            'step48_quality_profile' => 'gemini_vision_repair_identification_v1',
            'quality_gate' => [
                'enabled' => $qualityGate->enabled(),
                'verifier_providers' => $qualityGate->verifierProviders(),
                'trusted_final_providers' => $qualityGate->trustedFinalProviders(),
                'policy' => 'Generic/economical providers can be preliminary. Final recognition requires high quality score or stronger verification.',
            ],
            'image_detail' => (string) ($this->config['image_detail'] ?? 'original'),
            'web_search_enabled' => (bool) ($this->config['web_search_enabled'] ?? false),
            'reasoning_effort' => (string) ($this->config['reasoning_effort'] ?? ''),
            'max_images' => (int) ($this->config['max_images'] ?? 8),
            'max_image_bytes' => (int) ($this->config['max_image_bytes'] ?? 20971520),
            'billing_note' => 'Re-born routes across OCR, generic vision and optional trainable/specialized computer vision providers. Cloud free tiers and paid/custom providers remain rate-limited by each vendor.',
            'missing_configuration' => array_values(array_unique($missing)),
            'providers' => $providerStatuses,
        ];
    }

    public function analyze(RepairCase $case, array $attachments): ?array
    {
        if ((bool) ($this->config['enabled'] ?? true) !== true) {
            return null;
        }

        $provider = $this->providerName();
        if ($provider !== 'auto') {
            if (!isset($this->providers[$provider])) {
                return null;
            }

            $status = $this->providers[$provider]->status();
            $result = $this->providers[$provider]->analyze($case, $attachments) ?? null;
            if (is_array($result)) {
                $qualityGate = $this->qualityGate();
                if ($qualityGate->enabled()) {
                    $evaluation = $qualityGate->evaluate($provider, $result);
                    $evaluation['verification_status'] = $evaluation['accept_as_final'] ? 'final_single_provider' : 'single_provider_unverified';
                    $result['quality_gate'] = $evaluation;
                    if (!$evaluation['accept_as_final']) {
                        $result = $this->markPreliminary($result, 'Single provider mode returned a result that did not pass the final quality threshold.');
                    }
                }
                $result['ai_provider']['provider_router'] = [
                    'selected_provider' => $provider,
                    'provider_order' => [$provider],
                    'provider_attempts' => [$this->attemptFromResult($provider, $status, $result)],
                ];
            }

            return $result;
        }

        $qualityGate = $this->qualityGate();
        $lastFallback = null;
        $bestPreliminary = null;
        $bestPreliminaryProvider = '';
        $bestPreliminaryScore = -1;
        $attempts = [];
        $order = $this->providerOrder();

        foreach ($order as $index => $name) {
            if (!isset($this->providers[$name])) {
                $attempts[] = [
                    'provider' => $name,
                    'status' => 'skipped_missing_adapter',
                    'configured' => false,
                    'enabled' => false,
                    'reason' => 'Provider adapter is not registered in Re-born backend.',
                ];
                continue;
            }

            $status = $this->providers[$name]->status();
            if ((bool) ($status['enabled'] ?? false) !== true) {
                $attempts[] = $this->attemptFromStatus($name, $status, 'skipped_disabled', 'Provider is disabled in .env or globally disabled.');
                continue;
            }
            if ((bool) ($status['configured'] ?? false) !== true) {
                $missing = $status['missing_configuration'] ?? [];
                $reason = 'Provider is enabled but not configured.';
                if (is_array($missing) && $missing !== []) {
                    $reason .= ' Missing: ' . implode(', ', array_map('strval', $missing)) . '.';
                }
                $attempts[] = $this->attemptFromStatus($name, $status, 'skipped_not_configured', $reason);
                continue;
            }

            $startedAt = microtime(true);
            $result = $this->providers[$name]->analyze($case, $attachments);
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            if ($result === null) {
                $attempts[] = $this->attemptFromStatus($name, $status, 'no_result', 'Provider returned no result.', $elapsedMs);
                continue;
            }

            $attempt = $this->attemptFromResult($name, $status, $result, $elapsedMs);
            $mode = (string) ($result['recognition_mode'] ?? '');
            $providerStatus = (string) ($result['ai_provider']['status'] ?? '');
            $successfulLiveResult = !str_starts_with($mode, 'fallback_after_') && $providerStatus !== 'error_fallback';

            if ($successfulLiveResult && $qualityGate->enabled()) {
                $evaluation = $qualityGate->evaluate($name, $result);
                $remainingOrder = array_slice($order, $index + 1);
                $verifierAhead = $this->hasConfiguredVerifierAhead($remainingOrder, $qualityGate);
                $evaluation['verifier_available_ahead'] = $verifierAhead;
                $evaluation['remaining_provider_order'] = $remainingOrder;

                if ($evaluation['accept_as_final']) {
                    $evaluation['verification_status'] = 'final';
                    $result['quality_gate'] = $evaluation;
                    $attempt['quality_gate'] = $evaluation;
                    $attempts[] = $attempt;
                    $result['ai_provider']['provider_router'] = [
                        'selected_provider' => $name,
                        'selected_provider_finality' => 'final',
                        'provider_order' => $order,
                        'provider_attempts' => $attempts,
                    ];
                    return $result;
                }

                if ((bool) ($evaluation['accepted_as_preliminary'] ?? false) === true) {
                    $preliminary = $result;
                    $evaluation['verification_status'] = $verifierAhead ? 'pending_verifier' : 'preliminary_unverified';
                    $preliminary['quality_gate'] = $evaluation;
                    $attempt['quality_gate'] = $evaluation;

                    $score = (int) ($evaluation['quality_score'] ?? 0);
                    if ($score > $bestPreliminaryScore) {
                        $bestPreliminary = $preliminary;
                        $bestPreliminaryProvider = $name;
                        $bestPreliminaryScore = $score;
                    }

                    $attempt['status'] = $verifierAhead ? 'success_needs_verification' : 'success_preliminary_unverified';
                    $attempts[] = $attempt;

                    if ($verifierAhead) {
                        // Continue toward Gemini/OpenAI/specialized verification instead of stopping on a cheap provider.
                        continue;
                    }

                    if ($qualityGate->acceptPreliminaryWhenVerifierUnavailable()) {
                        $preliminary = $this->markPreliminary($preliminary, 'No configured stronger verifier was available after the preliminary provider.');
                        $preliminary['ai_provider']['provider_router'] = [
                            'selected_provider' => $name,
                            'selected_provider_finality' => 'preliminary_unverified',
                            'provider_order' => $order,
                            'provider_attempts' => $attempts,
                            'note' => 'Preliminary result returned because no stronger verifier was available.',
                        ];
                        return $preliminary;
                    }

                    continue;
                }

                $attempt['quality_gate'] = $evaluation;
                $attempt['status'] = 'success_rejected_by_quality_gate';
                $attempt['reason'] = 'Provider returned a live result, but the quality score was too low for final or preliminary use.';
                $attempts[] = $attempt;
                continue;
            }

            $attempts[] = $attempt;
            if ($successfulLiveResult) {
                // Backward compatible path if the quality gate is disabled.
                $result['ai_provider']['provider_router'] = [
                    'selected_provider' => $name,
                    'provider_order' => $order,
                    'provider_attempts' => $attempts,
                ];
                return $result;
            }

            $lastFallback = $result;
        }

        if (is_array($bestPreliminary) && $qualityGate->acceptPreliminaryWhenVerifierUnavailable()) {
            $bestPreliminary = $this->markPreliminary($bestPreliminary, 'Verifier providers did not produce a usable final result.');
            $bestPreliminary['quality_gate']['verification_status'] = 'preliminary_unverified';
            $bestPreliminary['quality_gate']['verifier_result'] = 'failed_or_rate_limited';
            $bestPreliminary['ai_provider']['provider_router'] = [
                'selected_provider' => $bestPreliminaryProvider,
                'selected_provider_finality' => 'preliminary_unverified',
                'provider_order' => $order,
                'provider_attempts' => $attempts,
                'note' => 'A preliminary recognition was preserved because stronger verifier providers failed, were rate-limited or were not configured.',
            ];
            return $bestPreliminary;
        }

        if (is_array($lastFallback)) {
            $lastFallback['ai_provider']['provider_router'] = [
                'selected_provider' => 'fallback_after_all_providers',
                'provider_order' => $order,
                'provider_attempts' => $attempts,
                'note' => 'All configured live providers failed or were not configured.',
            ];
            return $lastFallback;
        }

        return null;
    }

    /** @param list<string> $remainingOrder */
    private function hasConfiguredVerifierAhead(array $remainingOrder, RecognitionQualityGate $qualityGate): bool
    {
        $verifiers = $qualityGate->verifierProviders();
        foreach ($remainingOrder as $name) {
            $name = strtolower(trim((string) $name));
            if (!in_array($name, $verifiers, true) || !isset($this->providers[$name])) {
                continue;
            }
            $status = $this->providers[$name]->status();
            if ((bool) ($status['enabled'] ?? false) === true && (bool) ($status['configured'] ?? false) === true) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function markPreliminary(array $result, string $reason): array
    {
        $result['verification'] = [
            'status' => 'preliminary_unverified',
            'reason' => $reason,
            'message_it' => 'Riconoscimento preliminare: serve una verifica avanzata o manuale prima di considerarlo definitivo.',
        ];
        $result['repair_notes'][] = 'Recognition quality gate returned this as preliminary: ' . $reason;
        return $result;
    }

    private function qualityGate(): RecognitionQualityGate
    {
        $config = $this->config['quality_gate'] ?? [];
        return new RecognitionQualityGate(is_array($config) ? $config : []);
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function attemptFromStatus(string $name, array $status, string $attemptStatus, string $reason = '', ?int $elapsedMs = null): array
    {
        $attempt = [
            'provider' => $name,
            'status' => $attemptStatus,
            'enabled' => (bool) ($status['enabled'] ?? false),
            'configured' => (bool) ($status['configured'] ?? false),
            'mode' => (string) ($status['mode'] ?? ''),
            'model' => (string) ($status['model'] ?? ''),
        ];
        if ($reason !== '') {
            $attempt['reason'] = $reason;
        }
        if ($elapsedMs !== null) {
            $attempt['elapsed_ms'] = $elapsedMs;
        }
        $missing = $status['missing_configuration'] ?? [];
        if (is_array($missing) && $missing !== []) {
            $attempt['missing_configuration'] = array_values(array_map('strval', $missing));
        }

        return $attempt;
    }

    /** @param array<string, mixed> $status @param array<string, mixed> $result @return array<string, mixed> */
    private function attemptFromResult(string $name, array $status, array $result, ?int $elapsedMs = null): array
    {
        $mode = (string) ($result['recognition_mode'] ?? '');
        $providerStatus = (string) ($result['ai_provider']['status'] ?? '');
        $identificationStatus = (string) ($result['identification']['status'] ?? '');
        $attemptStatus = (!str_starts_with($mode, 'fallback_after_') && $providerStatus !== 'error_fallback') ? 'success' : 'failed';
        $reason = '';
        if ($attemptStatus === 'failed') {
            $reason = (string) ($result['ai_provider']['error'] ?? $result['identification']['why'] ?? 'Provider returned fallback or unusable result.');
        }

        $attempt = $this->attemptFromStatus($name, $status, $attemptStatus, $reason, $elapsedMs);
        $attempt['recognition_mode'] = $mode;
        $attempt['provider_status'] = $providerStatus;
        $attempt['identification_status'] = $identificationStatus;
        if (isset($result['object_guess']['label'])) {
            $attempt['part_guess'] = (string) $result['object_guess']['label'];
        }
        if (isset($result['identification']['visible_text']) && is_array($result['identification']['visible_text'])) {
            $attempt['visible_text_count'] = count($result['identification']['visible_text']);
        }

        return $attempt;
    }

    private function providerName(): string
    {
        $provider = strtolower(trim((string) ($this->config['provider'] ?? 'auto')));
        if ($provider === 'gemini_google') { return 'gemini'; }
        if ($provider === 'ocr_space') { return 'ocrspace'; }
        if (in_array($provider, ['aws', 'rekognition', 'aws_rekognition', 'aws_custom_labels'], true)) { return 'awsrekognition'; }
        if (in_array($provider, ['roboflow_hosted', 'roboflow_inference'], true)) { return 'roboflow'; }
        if (in_array($provider, ['clarifai_custom', 'clarifai_vision'], true)) { return 'clarifai'; }
        if ($provider === 'cloud_free') { return 'auto'; }
        return in_array($provider, ['auto', 'ocrspace', 'groq', 'openrouter', 'gemini', 'openai', 'awsrekognition', 'clarifai', 'roboflow'], true) ? $provider : 'auto';
    }

    /** @return list<string> */
    private function providerOrder(): array
    {
        $order = $this->config['provider_order'] ?? ['ocrspace', 'groq', 'openrouter'];
        if (!is_array($order)) {
            $order = ['ocrspace', 'groq', 'openrouter'];
        }

        $out = [];
        foreach ($order as $name) {
            $name = strtolower(trim((string) $name));
            if ($name === 'gemini_google') { $name = 'gemini'; }
            if ($name === 'ocr_space') { $name = 'ocrspace'; }
            if (in_array($name, ['aws', 'rekognition', 'aws_rekognition', 'aws_custom_labels'], true)) { $name = 'awsrekognition'; }
            if (in_array($name, ['roboflow_hosted', 'roboflow_inference'], true)) { $name = 'roboflow'; }
            if (in_array($name, ['clarifai_custom', 'clarifai_vision'], true)) { $name = 'clarifai'; }
            if (isset($this->providers[$name]) && !in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return $out !== [] ? $out : ['ocrspace', 'groq', 'openrouter'];
    }
}
