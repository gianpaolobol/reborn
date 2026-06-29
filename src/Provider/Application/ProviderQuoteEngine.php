<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use Reborn\Provider\Domain\ProviderMatch;

final class ProviderQuoteEngine
{
    /** @return array<string, mixed> */
    public function estimate(ProviderMatch $match, string $providerId): array
    {
        $result = $match->result;
        $providers = is_array($result['ranked_providers'] ?? null) ? $result['ranked_providers'] : [];
        $provider = null;
        foreach ($providers as $candidate) {
            if ((string) ($candidate['provider_id'] ?? '') === $providerId) {
                $provider = $candidate;
                break;
            }
        }

        if (!is_array($provider)) {
            throw new \InvalidArgumentException('Provider is not part of this match result.');
        }

        $context = is_array($result['repair_context'] ?? null) ? $result['repair_context'] : [];
        $production = max(900, (int) ($provider['estimated_quote_cents'] ?? 2990));
        $validation = !empty($context['dimensional_risk']) ? 900 : 500;
        $platformFee = max(250, (int) round(($production + $validation) * 0.12));
        $total = $production + $validation + $platformFee;

        return [
            'currency' => 'EUR',
            'provider' => [
                'id' => $providerId,
                'name' => (string) ($provider['name'] ?? 'Repair provider'),
                'city' => (string) ($provider['city'] ?? 'Local'),
                'country' => (string) ($provider['country'] ?? ''),
                'match_score' => (float) ($provider['match_score'] ?? 0),
            ],
            'repair_scope' => [
                'repair_case_id' => $match->repairCaseId,
                'repair_path_decision_id' => $match->repairPathDecisionId,
                'recommended_path' => (string) ($context['recommended_path'] ?? 'find_provider'),
                'selected_path_title' => (string) ($context['selected_path_title'] ?? 'Provider-assisted repair'),
            ],
            'line_items' => [
                ['label' => 'Provider validation and repair planning', 'amount_cents' => $validation],
                ['label' => 'Local repair production / fulfilment estimate', 'amount_cents' => $production],
                ['label' => 'Re-born platform fee', 'amount_cents' => $platformFee],
            ],
            'subtotal_cents' => $production + $validation,
            'platform_fee_cents' => $platformFee,
            'provider_payout_cents' => $production + $validation,
            'total_cents' => $total,
            'estimated_days' => (int) ($provider['estimated_days'] ?? 5),
            'assumptions' => [
                'Quote is preliminary until provider reviews photos, dimensions and material constraints.',
                'CAD or AI-generated geometry must be validated before production.',
                'Final order should close only when the object returns to function.',
            ],
        ];
    }
}
