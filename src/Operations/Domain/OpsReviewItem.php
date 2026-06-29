<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

final class OpsReviewItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly ?string $repairCaseId,
        public readonly ?string $providerId,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $status,
        public readonly string $title,
        public readonly string $description,
        public readonly array $payload,
        public readonly ?string $assignedTo,
        public readonly string $createdBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $resolvedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['source_type'],
            (string) $row['source_id'],
            $row['repair_case_id'] !== null ? (string) $row['repair_case_id'] : null,
            $row['provider_id'] !== null ? (string) $row['provider_id'] : null,
            (string) $row['category'],
            (string) $row['priority'],
            (string) $row['status'],
            (string) $row['title'],
            (string) $row['description'],
            json_decode((string) ($row['payload_json'] ?? '{}'), true) ?: [],
            $row['assigned_to'] !== null ? (string) $row['assigned_to'] : null,
            (string) $row['created_by'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
            $row['resolved_at'] !== null ? (string) $row['resolved_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'payload' => $this->payload,
            'assigned_to' => $this->assignedTo,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'resolved_at' => $this->resolvedAt,
        ];
    }
}
