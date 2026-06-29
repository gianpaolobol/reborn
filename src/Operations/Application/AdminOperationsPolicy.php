<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

final class AdminOperationsPolicy
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'policy_version' => 'admin_operations_moderation_v1',
            'purpose' => 'Turn Re-born from a demo journey into a governable operating system for real repairs, providers and customer risk.',
            'admin_only_mutations' => true,
            'review_item_statuses' => ['open', 'in_review', 'escalated', 'resolved'],
            'priority_sla' => [
                'critical' => '4 business hours',
                'high' => '1 business day',
                'medium' => '3 business days',
                'low' => '7 business days',
            ],
            'review_categories' => ['safety', 'quality', 'content', 'provider_dispute', 'payment_risk', 'policy', 'manual_review'],
            'moderation_actions' => ['approve', 'suppress', 'require_changes', 'flag_provider', 'refund_review', 'policy_note', 'dismiss'],
            'escalation_levels' => ['ops_lead', 'policy_lead', 'safety_lead', 'founder_review'],
        ];
    }
}
