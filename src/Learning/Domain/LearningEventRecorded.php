<?php

declare(strict_types=1);

namespace Reborn\Learning\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class LearningEventRecorded implements DomainEvent
{
    public function __construct(
        private readonly string $learningEventId,
        private readonly string $completionReportId,
        private readonly string $repairCaseId,
        private readonly string $eventType,
        private readonly float $confidenceDelta,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'learning.event_recorded';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'learning_event_id' => $this->learningEventId,
            'completion_report_id' => $this->completionReportId,
            'repair_case_id' => $this->repairCaseId,
            'event_type' => $this->eventType,
            'confidence_delta' => $this->confidenceDelta,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
