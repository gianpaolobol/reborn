<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class RepairPathDecisionRequested implements DomainEvent
{
    public function __construct(
        private readonly string $repairCaseId,
        private readonly ?string $recognitionJobId,
        private readonly string $requestedBy,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'repair.path_decision_requested';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'recognition_job_id' => $this->recognitionJobId,
            'requested_by' => $this->requestedBy,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
