<?php

declare(strict_types=1);

namespace Reborn\Learning\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class RepairCompletionReported implements DomainEvent
{
    public function __construct(
        private readonly string $completionReportId,
        private readonly string $fulfilmentId,
        private readonly string $repairOrderId,
        private readonly string $repairCaseId,
        private readonly string $providerId,
        private readonly string $outcomeStatus,
        private readonly string $actorId,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'repair.completion_reported';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'completion_report_id' => $this->completionReportId,
            'fulfilment_id' => $this->fulfilmentId,
            'repair_order_id' => $this->repairOrderId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'outcome_status' => $this->outcomeStatus,
            'actor_id' => $this->actorId,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
