<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use PDO;
use Reborn\Marketplace\Domain\RepairPathDecision;
use Reborn\Repair\Domain\RepairCase;

final class ProviderMatchEngine
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{repair_context: array<string, mixed>, ranked_providers: list<array<string, mixed>>, guardrails: list<string>} */
    public function match(RepairCase $case, ?RepairPathDecision $decision): array
    {
        $context = $this->repairContext($case, $decision);
        $stmt = $this->pdo->query('SELECT id, name, city, country, capabilities, rating, average_lead_time_days FROM providers ORDER BY rating DESC, average_lead_time_days ASC');
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ranked = [];
        foreach ($providers as $provider) {
            $ranked[] = $this->scoreProvider($provider, $context);
        }

        usort($ranked, static fn(array $a, array $b): int => $b['match_score'] <=> $a['match_score']);

        return [
            'repair_context' => $context,
            'ranked_providers' => array_slice($ranked, 0, 5),
            'guardrails' => [
                'Provider matching is a repair fulfilment step, not a generic print marketplace search.',
                'Every quote remains preliminary until the provider validates geometry, material and tolerance constraints.',
                'Independent makers and professional services compete on explicit trust, capabilities, lead time and repair fit.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function repairContext(RepairCase $case, ?RepairPathDecision $decision): array
    {
        $result = $decision?->result ?? [];
        $recommendedPath = (string) ($result['recommended_path'] ?? 'find_provider');
        $rankedPaths = is_array($result['ranked_paths'] ?? null) ? $result['ranked_paths'] : [];
        $topPath = $rankedPaths[0] ?? [];
        $factors = is_array($result['decision_factors'] ?? null) ? $result['decision_factors'] : [];

        return [
            'repair_case_id' => $case->id,
            'title' => $case->title,
            'category' => $case->category,
            'status' => $case->status,
            'recognized_product' => $case->recognizedProduct,
            'recognized_component' => $case->recognizedComponent,
            'recommended_path' => $recommendedPath,
            'selected_path_title' => (string) ($topPath['title'] ?? 'Find a local repair provider'),
            'estimated_price_cents' => (int) ($topPath['estimated_price_cents'] ?? 2990),
            'estimated_days' => (int) ($topPath['estimated_days'] ?? 4),
            'repairability_score' => (float) ($factors['repairability_score'] ?? 0.64),
            'dimensional_risk' => (bool) ($factors['dimensional_risk'] ?? true),
            'requires_provider_validation' => true,
        ];
    }

    /** @param array<string, mixed> $provider @param array<string, mixed> $context @return array<string, mixed> */
    private function scoreProvider(array $provider, array $context): array
    {
        $capabilities = json_decode((string) ($provider['capabilities'] ?? '[]'), true);
        if (!is_array($capabilities)) {
            $capabilities = [];
        }

        $capabilitiesLower = array_map(static fn(mixed $item): string => strtolower((string) $item), $capabilities);
        $recommendedPath = strtolower((string) ($context['recommended_path'] ?? 'find_provider'));
        $title = strtolower((string) ($context['title'] ?? '') . ' ' . (string) ($context['recognized_component'] ?? '') . ' ' . (string) ($context['category'] ?? ''));

        $capabilityScore = 0.18;
        $matched = [];
        foreach ($capabilitiesLower as $index => $capability) {
            if (str_contains($capability, 'cad') || str_contains($capability, 'validation')) {
                $capabilityScore += 0.18;
                $matched[] = (string) ($capabilities[$index] ?? 'CAD validation');
            }
            if (str_contains($capability, 'fdm') && ($recommendedPath === 'generate_part' || str_contains($title, 'plastic') || str_contains($title, 'cover') || str_contains($title, 'knob'))) {
                $capabilityScore += 0.16;
                $matched[] = (string) ($capabilities[$index] ?? 'FDM');
            }
            if (str_contains($capability, 'sls') || str_contains($capability, 'sla')) {
                $capabilityScore += 0.08;
                $matched[] = (string) ($capabilities[$index] ?? 'Professional additive');
            }
            if (str_contains($capability, 'repair')) {
                $capabilityScore += 0.14;
                $matched[] = (string) ($capabilities[$index] ?? 'repair validation');
            }
        }

        $rating = (float) ($provider['rating'] ?? 0);
        $leadTime = max(1, (int) ($provider['average_lead_time_days'] ?? 5));
        $leadScore = max(0.0, 0.18 - (($leadTime - 1) * 0.025));
        $ratingScore = min(0.30, ($rating / 5) * 0.30);
        $riskPenalty = !empty($context['dimensional_risk']) ? 0.04 : 0.0;
        $score = max(0.22, min(0.98, $capabilityScore + $ratingScore + $leadScore - $riskPenalty));

        $baseQuote = max(1500, (int) ($context['estimated_price_cents'] ?? 2990));
        $providerMultiplier = 1 + ((5 - min(5, $rating)) * 0.05) + (($leadTime > 4 ? 0.06 : 0));
        $estimatedQuote = (int) round($baseQuote * $providerMultiplier + 690);

        $matched = array_values(array_unique($matched));
        if ($matched === []) {
            $matched = ['general repair intake'];
        }

        return [
            'provider_id' => (string) $provider['id'],
            'name' => (string) $provider['name'],
            'city' => (string) $provider['city'],
            'country' => (string) $provider['country'],
            'capabilities' => $capabilities,
            'matched_capabilities' => $matched,
            'rating' => $rating,
            'average_lead_time_days' => $leadTime,
            'match_score' => round($score, 2),
            'estimated_quote_cents' => $estimatedQuote,
            'estimated_days' => max(2, $leadTime + (!empty($context['dimensional_risk']) ? 1 : 0)),
            'match_reason' => 'Compatible repair capabilities, trust score and lead time for the selected repair path.',
            'quality_checks' => [
                'confirm_dimensions_before_production',
                'validate_material_and_tolerance',
                'return repaired object to function before closing order',
            ],
        ];
    }
}
