<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\AI\Domain\RecognitionJob;
use Reborn\Repair\Domain\RepairCase;

final class RepairPathDecisionEngine
{
    /** @return array{decision_factors: array<string, mixed>, recommended_path: string, ranked_paths: list<array<string, mixed>>, guardrails: list<string>} */
    public function decide(RepairCase $case, ?RecognitionJob $recognitionJob): array
    {
        $result = $recognitionJob?->result ?? [];
        $objectGuess = is_array($result['object_guess'] ?? null) ? $result['object_guess'] : [];
        $damage = is_array($result['damage_assessment'] ?? null) ? $result['damage_assessment'] : [];
        $nextStep = is_array($result['recommended_next_step'] ?? null) ? $result['recommended_next_step'] : [];

        $confidence = max(0.0, min(1.0, (float) ($objectGuess['confidence'] ?? $case->confidenceScore ?: 0.42)));
        $repairability = max(0.0, min(1.0, (float) ($damage['repairability_score'] ?? 0.58)));
        $damageType = (string) ($damage['type'] ?? 'unknown');
        $severity = (string) ($damage['severity'] ?? 'medium');
        $recommendedInputPath = (string) ($nextStep['path'] ?? 'identify_part');
        $category = $case->category;
        $titleText = strtolower($case->title . ' ' . $case->description . ' ' . $category . ' ' . (string) ($objectGuess['label'] ?? ''));

        $hasBatchSignals = str_contains($titleText, 'batch') || str_contains($titleText, 'fleet') || str_contains($titleText, 'facility') || str_contains($titleText, 'enterprise');
        $hasDimensionalRisk = $confidence < 0.62 || $recommendedInputPath === 'ask_more_photos';
        $isPlasticComponent = str_contains($titleText, 'plastic') || str_contains($titleText, 'cover') || str_contains($titleText, 'knob') || str_contains($titleText, 'hinge') || str_contains($titleText, 'case');
        $isProviderFriendly = in_array($category, ['home_appliance', 'consumer_electronics', 'furniture', 'sport', 'tooling'], true);

        $paths = [
            $this->path(
                'identify_part',
                'Find an existing verified part',
                'Search the Repair Knowledge Graph and verified sources before creating new geometry.',
                0.48 + ($confidence * 0.30) + ($recommendedInputPath === 'identify_part' ? 0.16 : 0.04),
                1200,
                3,
                ['Check graph match', 'Compare dimensions', 'Prefer verified replacement if fit is confirmed'],
                ['fit_unknown' => $hasDimensionalRisk]
            ),
            $this->path(
                'generate_part',
                'Generate a repair model with AI fallback',
                'Use AI-assisted CAD only when no verified part is available and the repair evidence is strong enough.',
                0.38 + ($repairability * 0.28) + ($isPlasticComponent ? 0.16 : 0.02) + ($recommendedInputPath === 'generate_part' ? 0.14 : 0.0),
                2490,
                5,
                ['Create constrained CAD draft', 'Check wall thickness and tolerances', 'Require provider validation before fulfilment'],
                ['human_validation_required' => true]
            ),
            $this->path(
                'ask_maker',
                'Ask a specialist maker to model the component',
                'Route the repair evidence to makers when AI can identify the component but geometry needs expert judgement.',
                0.36 + ($repairability * 0.24) + ($confidence >= 0.68 ? 0.14 : 0.06),
                3490,
                6,
                ['Open maker brief', 'Attach photos and measurements', 'Enable royalty if the model becomes reusable'],
                ['maker_review_required' => true]
            ),
            $this->path(
                'find_provider',
                'Find a local repair provider',
                'Match the case to a local provider for inspection, material choice, production and delivery.',
                0.40 + ($repairability * 0.22) + ($isProviderFriendly ? 0.18 : 0.04) + ($recommendedInputPath === 'find_provider' ? 0.14 : 0.0),
                2990,
                4,
                ['Select provider by capability', 'Validate material and safety constraints', 'Quote repair order'],
                ['provider_quote_required' => true]
            ),
            $this->path(
                'open_bounty',
                'Open a community repair bounty',
                'Ask the Re-born community to solve the repair when knowledge is missing or multiple objects share the same issue.',
                0.30 + ($hasDimensionalRisk ? 0.18 : 0.06) + ($damageType === 'unknown' ? 0.10 : 0.02),
                990,
                10,
                ['Publish anonymized repair challenge', 'Collect maker proposals', 'Promote solved model into the graph'],
                ['not_instant_repair' => true]
            ),
            $this->path(
                'enterprise_escalation',
                'Escalate to enterprise repair workflow',
                'Use this path for repeatable repair cases, batch demand, warranties or operational fleets.',
                0.22 + ($hasBatchSignals ? 0.36 : 0.04) + ($category === 'home_appliance' ? 0.06 : 0.02),
                4990,
                7,
                ['Create repeatable case template', 'Capture approvals and SLA', 'Track objects saved and cost avoidance'],
                ['enterprise_context_required' => !$hasBatchSignals]
            ),
        ];

        foreach ($paths as &$path) {
            $path['score'] = round(max(0.18, min(0.97, (float) $path['score'])), 2);
        }
        unset($path);

        usort($paths, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return [
            'decision_factors' => [
                'recognition_confidence' => round($confidence, 2),
                'repairability_score' => round($repairability, 2),
                'damage_type' => $damageType,
                'severity' => $severity,
                'category' => $category,
                'input_recommended_next_step' => $recommendedInputPath,
                'dimensional_risk' => $hasDimensionalRisk,
            ],
            'recommended_path' => (string) $paths[0]['type'],
            'ranked_paths' => $paths,
            'guardrails' => [
                'Do not sell a file before the repair path is validated.',
                'AI-generated geometry remains a draft until human/provider checks confirm dimensions, material and safety constraints.',
                'Every completed repair must update Repair DNA and Knowledge Graph confidence.',
            ],
        ];
    }

    /** @param list<string> $nextActions @param array<string, bool> $riskFlags @return array<string, mixed> */
    private function path(string $type, string $title, string $description, float $score, int $priceCents, int $days, array $nextActions, array $riskFlags): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'score' => $score,
            'estimated_price_cents' => $priceCents,
            'estimated_days' => $days,
            'next_actions' => $nextActions,
            'risk_flags' => $riskFlags,
        ];
    }
}
