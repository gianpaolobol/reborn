<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class RepairPathDecisionCompleted implements DomainEvent
{
    public function __construct(
        private readonly string $repairCaseId,
        private readonly string $decisionId,
        private readonly string $recommendedPath,
        private readonly float $topScore,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'repair.path_decision_completed';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'decision_id' => $this->decisionId,
            'recommended_path' => $this->recommendedPath,
            'top_score' => $this->topScore,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
