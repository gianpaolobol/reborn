<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

/**
 * Scores a live recognition result before the router is allowed to stop.
 *
 * The goal is to prevent fast/cheap providers from becoming the final truth when
 * they produced a plausible but weak or unverifiable answer. Generic providers
 * can still be used as a preliminary result if all verification providers fail
 * because of quota/rate limits.
 */
final class RecognitionQualityGate
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = [])
    {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /** @return list<string> */
    public function verifierProviders(): array
    {
        return $this->csv($this->config['verifier_providers'] ?? ['gemini', 'openai', 'awsrekognition', 'clarifai', 'roboflow']);
    }

    /** @return list<string> */
    public function trustedFinalProviders(): array
    {
        return $this->csv($this->config['trusted_final_providers'] ?? ['gemini', 'openai', 'awsrekognition', 'clarifai', 'roboflow']);
    }

    /** @return list<string> */
    public function lowTrustProviders(): array
    {
        return $this->csv($this->config['low_trust_providers'] ?? ['ocrspace', 'groq', 'openrouter']);
    }

    public function acceptPreliminaryWhenVerifierUnavailable(): bool
    {
        return (bool) ($this->config['accept_preliminary_when_verifier_unavailable'] ?? true);
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    public function evaluate(string $provider, array $result): array
    {
        $provider = strtolower(trim($provider));
        $trustedFinal = in_array($provider, $this->trustedFinalProviders(), true);
        $lowTrust = in_array($provider, $this->lowTrustProviders(), true);

        $identificationStatus = strtolower(trim((string) ($result['identification']['status'] ?? 'unclear')));
        $providerStatus = strtolower(trim((string) ($result['ai_provider']['status'] ?? '')));
        $recognitionMode = strtolower(trim((string) ($result['recognition_mode'] ?? '')));
        $liveSuccess = $identificationStatus === 'recognized'
            && $providerStatus !== 'error_fallback'
            && !str_starts_with($recognitionMode, 'fallback_after_');

        $part = $this->firstNonEmpty([
            $result['part_spec']['name_it'] ?? '',
            $result['part_spec']['name_en'] ?? '',
            $result['identification']['commercial_name'] ?? '',
            $result['object_guess']['label'] ?? '',
        ]);
        $function = $this->firstNonEmpty([
            $result['replacement_part_brief']['probable_function'] ?? '',
            $result['part_spec']['appliance_context'] ?? '',
            $result['object_guess']['object_context'] ?? '',
            $result['replacement_part_brief']['part_family'] ?? '',
        ]);
        $partNumber = trim((string) ($result['identification']['part_number'] ?? ''));
        $visibleText = $this->stringList($result['identification']['visible_text'] ?? []);
        $brands = $this->stringList($result['identification']['possible_brands'] ?? []);
        $sourceImageType = strtolower(trim((string) ($result['identification']['source_image_type'] ?? '')));
        $confidence = (float) ($result['object_guess']['confidence'] ?? 0);
        if ($confidence > 1.0) {
            $confidence = $confidence / 100.0;
        }
        $confidence = max(0.0, min(1.0, $confidence));

        $hasVisibleCode = $partNumber !== '' || $this->visibleTextLooksLikeCode($visibleText);
        $hasBrand = $brands !== [] || $this->visibleTextLooksLikeBrand($visibleText);
        $partGeneric = $this->isGenericPart($part);
        $functionMissing = $this->isMissingFunction($function);
        $specificPartName = !$partGeneric && strlen($part) >= 6;

        // Provider confidence is often self-reported by generic VLMs and can be
        // overconfident on visually plausible but wrong identifications. Keep it
        // as metadata, but do not let low-trust providers finalize only because
        // they declared high confidence.
        $ignoreLowTrustSelfConfidence = (bool) ($this->config['ignore_low_trust_self_confidence'] ?? true);
        $requireTrustedVerifierForFinal = (bool) ($this->config['require_trusted_verifier_for_final'] ?? true);
        $lowTrustMaxScore = (int) ($this->config['low_trust_max_score'] ?? 79);

        $score = 0;
        $reasons = [];

        if ($liveSuccess) { $score += 25; $reasons[] = 'provider returned recognized live result'; }
        if ($specificPartName) { $score += 20; $reasons[] = 'specific part name present'; } else { $score -= 15; $reasons[] = 'part name is generic or missing'; }
        if (!$functionMissing) { $score += 14; $reasons[] = 'function/context present'; } else { $score -= 12; $reasons[] = 'function is missing or generic'; }
        if ($partNumber !== '') { $score += 22; $reasons[] = 'part code read'; }
        if ($visibleText !== []) { $score += min(8, count($visibleText) * 4); $reasons[] = 'visible text captured'; }
        if ($hasBrand) { $score += 10; $reasons[] = 'brand signal present'; }
        if ($confidence > 0 && (!$lowTrust || !$ignoreLowTrustSelfConfidence)) {
            $score += (int) round($confidence * 10);
            $reasons[] = 'provider confidence signal present';
        } elseif ($confidence > 0 && $lowTrust && $ignoreLowTrustSelfConfidence) {
            $reasons[] = 'self-reported confidence ignored for low-trust provider';
        }
        if ($sourceImageType !== '' && $sourceImageType !== 'unknown') { $score += 5; $reasons[] = 'source image type is useful'; }
        if ($trustedFinal) { $score += 5; $reasons[] = 'provider is trusted as final verifier'; }
        if ($provider === 'ocrspace') { $score -= 18; $reasons[] = 'OCR-only provider cannot finalize object identity'; }
        if (!$liveSuccess) { $score -= 30; $reasons[] = 'provider did not return a successful live recognition'; }

        $score = max(0, min(100, $score));
        if ($lowTrust && !$trustedFinal && $lowTrustMaxScore > 0 && $score > $lowTrustMaxScore) {
            $score = $lowTrustMaxScore;
            $reasons[] = 'low-trust provider score capped until independently verified';
        }

        $minFinal = (int) ($this->config['minimum_final_score'] ?? 82);
        $minPreliminary = (int) ($this->config['minimum_preliminary_score'] ?? 58);
        $confidenceThreshold = (float) ($this->config['provider_confidence_threshold'] ?? 0.82);
        if ($confidenceThreshold > 1.0) {
            $confidenceThreshold = $confidenceThreshold / 100.0;
        }

        $verificationReasons = [];
        if ($lowTrust) {
            $verificationReasons[] = 'provider is economical/generic and needs a quality gate';
        }
        if ($requireTrustedVerifierForFinal && !$trustedFinal) {
            $verificationReasons[] = 'provider is not listed as a trusted final verifier';
        }
        if ((bool) ($this->config['verify_when_code_visible'] ?? true) && $lowTrust && $hasVisibleCode) {
            $verificationReasons[] = 'code or visible text is present and should be verified by a stronger model';
        }
        if ((bool) ($this->config['verify_when_generic_part'] ?? true) && ($partGeneric || $score < $minFinal)) {
            $verificationReasons[] = 'recognition is generic or below final confidence threshold';
        }
        if ((bool) ($this->config['verify_when_missing_function'] ?? true) && $functionMissing) {
            $verificationReasons[] = 'function is missing or too generic';
        }
        if ($confidence > 0 && $confidence < $confidenceThreshold) {
            $verificationReasons[] = 'provider confidence is below threshold';
        }
        if ($lowTrust && $ignoreLowTrustSelfConfidence && $confidence >= $confidenceThreshold) {
            $verificationReasons[] = 'high confidence is self-reported by a low-trust provider and needs independent verification';
        }

        $requiresVerification = $liveSuccess
            && $verificationReasons !== []
            && ($lowTrust || ($requireTrustedVerifierForFinal && !$trustedFinal));

        $canFinalizeByHighScore = !$requireTrustedVerifierForFinal && $score >= $minFinal;
        $acceptAsFinal = $liveSuccess
            && !$requiresVerification
            && ($trustedFinal || $canFinalizeByHighScore);

        $acceptedAsPreliminary = $liveSuccess
            && !$acceptAsFinal
            && $score >= $minPreliminary;

        return [
            'enabled' => $this->enabled(),
            'provider' => $provider,
            'quality_score' => $score,
            'minimum_final_score' => $minFinal,
            'minimum_preliminary_score' => $minPreliminary,
            'accept_as_final' => $acceptAsFinal,
            'accepted_as_preliminary' => $acceptedAsPreliminary,
            'requires_verification' => $requiresVerification,
            'verification_status' => $acceptAsFinal ? 'final' : ($acceptedAsPreliminary ? 'preliminary_needs_verification' : 'not_accepted'),
            'verification_reasons' => array_values(array_unique($verificationReasons)),
            'score_reasons' => array_values(array_unique($reasons)),
            'signals' => [
                'recognized' => $liveSuccess,
                'trusted_final_provider' => $trustedFinal,
                'low_trust_provider' => $lowTrust,
                'part' => $part,
                'function' => $function,
                'part_number' => $partNumber,
                'has_visible_code' => $hasVisibleCode,
                'has_brand' => $hasBrand,
                'part_generic' => $partGeneric,
                'function_missing' => $functionMissing,
                'confidence' => $confidence,
                'ignore_low_trust_self_confidence' => $ignoreLowTrustSelfConfidence,
                'require_trusted_verifier_for_final' => $requireTrustedVerifierForFinal,
                'low_trust_max_score' => $lowTrustMaxScore,
            ],
            'policy' => 'Cheap/generic providers can produce preliminary recognition only. Final recognition requires a trusted verifier such as Gemini/OpenAI or a trainable/specialized provider configured as trusted.',
        ];
    }

    /** @param array<int|string, mixed> $values @return list<string> */
    private function csv(mixed $values): array
    {
        if (is_string($values)) {
            $values = explode(',', $values);
        }
        if (!is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            $value = strtolower(trim((string) $value));
            if ($value !== '' && !in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /** @param list<mixed> $values */
    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /** @param mixed $values @return list<string> */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return array_values(array_unique($out));
    }

    /** @param list<string> $visibleText */
    private function visibleTextLooksLikeCode(array $visibleText): bool
    {
        foreach ($visibleText as $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            if (preg_match('/[A-Z]{1,6}[-_ ]?[A-Z0-9]{2,}/i', $text) === 1 && preg_match('/\d/', $text) === 1) {
                return true;
            }
            if (preg_match('/\b[A-Z]{2,}\b/', $text) === 1 && preg_match('/\b\d{2,}\b/', $text) === 1) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $visibleText */
    private function visibleTextLooksLikeBrand(array $visibleText): bool
    {
        $knownBrands = ['shimano', 'sea to summit', 'bosch', 'whirlpool', 'electrolux', 'bialetti', 'ikea', 'decathlon', 'garmin'];
        $text = strtolower(implode(' ', $visibleText));
        foreach ($knownBrands as $brand) {
            if (str_contains($text, $brand)) {
                return true;
            }
        }
        return false;
    }

    private function isGenericPart(string $part): bool
    {
        $value = strtolower(trim($part));
        if ($value === '' || strlen($value) < 4) {
            return true;
        }
        $genericExact = [
            'component', 'component to confirm', 'componente', 'componente da confermare',
            'componente da immagine', 'pezzo', 'pezzo da confermare', 'object', 'unknown',
            'unknown part', 'part', 'parte plastica', 'plastic part', 'ricambio da confermare',
        ];
        if (in_array($value, $genericExact, true)) {
            return true;
        }
        foreach (['da confermare', 'to confirm', 'unknown', 'generic', 'sconosciut'] as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function isMissingFunction(string $function): bool
    {
        $value = strtolower(trim($function));
        if ($value === '' || strlen($value) < 4) {
            return true;
        }
        foreach (['da confermare', 'to confirm', 'unknown', 'non disponibile', 'not available', 'funzione da confermare'] as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }
        return false;
    }
}
