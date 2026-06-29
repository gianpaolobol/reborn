<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Domain;

final class RepairFulfilment
{
    /** @param list<array<string, mixed>> $timeline */
    public function __construct(
        public readonly string $id,
        public readonly string $repairOrderId,
        public readonly string $quoteRequestId,
        public readonly string $repairCaseId,
        public readonly string $providerId,
        public readonly string $requestedBy,
        public readonly ?string $acceptedBy,
        public readonly string $status,
        public readonly ?string $providerNotes,
        public readonly ?string $trackingReference,
        public readonly array $timeline,
        public readonly string $createdAt,
        public readonly ?string $acceptedAt,
        public readonly ?string $startedAt,
        public readonly ?string $qualityCheckedAt,
        public readonly ?string $readyAt,
        public readonly ?string $completedAt,
        public readonly ?string $rejectedAt,
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $timeline = json_decode((string) ($row['timeline_json'] ?? '[]'), true);
        if (!is_array($timeline)) {
            $timeline = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['repair_order_id'],
            (string) $row['quote_request_id'],
            (string) $row['repair_case_id'],
            (string) $row['provider_id'],
            (string) $row['requested_by'],
            $row['accepted_by'] !== null ? (string) $row['accepted_by'] : null,
            (string) $row['status'],
            $row['provider_notes'] !== null ? (string) $row['provider_notes'] : null,
            $row['tracking_reference'] !== null ? (string) $row['tracking_reference'] : null,
            array_values($timeline),
            (string) $row['created_at'],
            $row['accepted_at'] !== null ? (string) $row['accepted_at'] : null,
            $row['started_at'] !== null ? (string) $row['started_at'] : null,
            $row['quality_checked_at'] !== null ? (string) $row['quality_checked_at'] : null,
            $row['ready_at'] !== null ? (string) $row['ready_at'] : null,
            $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
            $row['rejected_at'] !== null ? (string) $row['rejected_at'] : null,
            (string) $row['updated_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'repair_order_id' => $this->repairOrderId,
            'quote_request_id' => $this->quoteRequestId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'requested_by' => $this->requestedBy,
            'accepted_by' => $this->acceptedBy,
            'status' => $this->status,
            'provider_notes' => $this->providerNotes,
            'tracking_reference' => $this->trackingReference,
            'timeline_json' => $this->timeline,
            'created_at' => $this->createdAt,
            'accepted_at' => $this->acceptedAt,
            'started_at' => $this->startedAt,
            'quality_checked_at' => $this->qualityCheckedAt,
            'ready_at' => $this->readyAt,
            'completed_at' => $this->completedAt,
            'rejected_at' => $this->rejectedAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
