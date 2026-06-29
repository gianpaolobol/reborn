<?php

declare(strict_types=1);

namespace Reborn\Governance\Application;

final class MarketplaceGovernancePolicy
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'policy_version' => 'marketplace_governance_v1',
            'purpose' => 'Rank providers by repair fit, verified outcomes and governance actions before routing real repair demand.',
            'ranking_formula' => [
                'quality_score_weight' => 0.50,
                'reliability_score_weight' => 0.25,
                'communication_score_weight' => 0.10,
                'timeliness_score_weight' => 0.10,
                'seed_rating_weight_when_no_reviews' => 0.05,
                'governance_adjustment' => 'manual active actions may add or subtract up to 100 points',
            ],
            'routing_status_rules' => [
                'suppressed' => 'Provider has an active suppress action or final score below 35.',
                'watchlist' => 'Provider has active watchlist/quality review action or final score below 60.',
                'eligible' => 'Provider can receive matches and quote requests.',
            ],
            'allowed_actions' => ['watchlist', 'suppress', 'manual_boost', 'manual_penalty', 'quality_review', 'policy_note'],
            'admin_only_mutations' => true,
        ];
    }
}
