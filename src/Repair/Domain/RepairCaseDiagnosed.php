<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class RepairCaseDiagnosed implements DomainEvent
{
    public function __construct(private readonly string $repairCaseId, private readonly float $confidenceScore, private readonly string $occurredAt)
    {
    }

    public function name(): string
    {
        return 'repair.case.diagnosed';
    }

    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'confidence_score' => $this->confidenceScore,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
