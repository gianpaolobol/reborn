<?php

declare(strict_types=1);

namespace Reborn\Learning\Domain;

final class RepairLearningEvent
{
    /** @param array<string, mixed> $signal */
    public function __construct(
        public readonly string $id,
        public readonly string $completionReportId,
        public readonly string $fulfilmentId,
        public readonly string $repairCaseId,
        public readonly string $providerId,
        public readonly string $eventType,
        public readonly array $signal,
        public readonly float $confidenceDelta,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $signal = json_decode((string) ($row['signal_json'] ?? '{}'), true);
        if (!is_array($signal)) {
            $signal = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['completion_report_id'],
            (string) $row['fulfilment_id'],
            (string) $row['repair_case_id'],
            (string) $row['provider_id'],
            (string) $row['event_type'],
            $signal,
            (float) $row['confidence_delta'],
            (string) $row['created_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'completion_report_id' => $this->completionReportId,
            'fulfilment_id' => $this->fulfilmentId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'event_type' => $this->eventType,
            'signal_json' => $this->signal,
            'confidence_delta' => $this->confidenceDelta,
            'created_at' => $this->createdAt,
        ];
    }
}
