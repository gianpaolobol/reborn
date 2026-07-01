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

        return [
            'provider' => $provider,
            'capability' => 'photo_to_replacement_part_brief',
            'enabled' => (bool) ($this->config['enabled'] ?? true),
            'configured' => $configuredSelectedProvider,
            'mode' => $provider === 'auto' ? 'auto_provider_router' : 'single_provider_router',
            'provider_order' => $this->providerOrder(),
            'quality_profile' => 'max_vision_ocr_reference_part_identification_v2',
            'step48_quality_profile' => 'gemini_vision_repair_identification_v1',
            'image_detail' => (string) ($this->config['image_detail'] ?? 'original'),
            'web_search_enabled' => (bool) ($this->config['web_search_enabled'] ?? false),
            'reasoning_effort' => (string) ($this->config['reasoning_effort'] ?? ''),
            'max_images' => (int) ($this->config['max_images'] ?? 8),
            'max_image_bytes' => (int) ($this->config['max_image_bytes'] ?? 20971520),
            'billing_note' => 'Step 50 uses OCR.space, Groq and OpenRouter as free-cloud first providers. All cloud free tiers still have provider-side limits; Re-born now routes across providers and falls back gracefully.',
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
            return $this->providers[$provider]->analyze($case, $attachments) ?? null;
        }

        $lastFallback = null;
        foreach ($this->providerOrder() as $name) {
            if (!isset($this->providers[$name])) {
                continue;
            }

            $result = $this->providers[$name]->analyze($case, $attachments);
            if ($result === null) {
                continue;
            }

            $mode = (string) ($result['recognition_mode'] ?? '');
            $providerStatus = (string) ($result['ai_provider']['status'] ?? '');
            if (!str_starts_with($mode, 'fallback_after_') && $providerStatus !== 'error_fallback') {
                $result['ai_provider']['provider_router'] = [
                    'selected_provider' => $name,
                    'provider_order' => $this->providerOrder(),
                ];
                return $result;
            }

            $lastFallback = $result;
        }

        if (is_array($lastFallback)) {
            $lastFallback['ai_provider']['provider_router'] = [
                'selected_provider' => 'fallback_after_all_providers',
                'provider_order' => $this->providerOrder(),
                'note' => 'All configured live providers failed or were not configured.',
            ];
            return $lastFallback;
        }

        return null;
    }

    private function providerName(): string
    {
        $provider = strtolower(trim((string) ($this->config['provider'] ?? 'auto')));
        if ($provider === 'gemini_google') { return 'gemini'; }
        if ($provider === 'ocr_space') { return 'ocrspace'; }
        if ($provider === 'cloud_free') { return 'auto'; }
        return in_array($provider, ['auto', 'ocrspace', 'groq', 'openrouter', 'gemini', 'openai'], true) ? $provider : 'auto';
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
            if (isset($this->providers[$name]) && !in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return $out !== [] ? $out : ['ocrspace', 'groq', 'openrouter'];
    }
}
