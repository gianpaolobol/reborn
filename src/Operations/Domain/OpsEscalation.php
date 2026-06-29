<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

final class OpsEscalation
{
    public function __construct(
        public readonly string $id,
        public readonly string $reviewItemId,
        public readonly string $escalationLevel,
        public readonly string $status,
        public readonly string $reason,
        public readonly ?string $assignedTo,
        public readonly string $createdBy,
        public readonly string $createdAt,
        public readonly ?string $resolvedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['review_item_id'],
            (string) $row['escalation_level'],
            (string) $row['status'],
            (string) $row['reason'],
            $row['assigned_to'] !== null ? (string) $row['assigned_to'] : null,
            (string) $row['created_by'],
            (string) $row['created_at'],
            $row['resolved_at'] !== null ? (string) $row['resolved_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'review_item_id' => $this->reviewItemId,
            'escalation_level' => $this->escalationLevel,
            'status' => $this->status,
            'reason' => $this->reason,
            'assigned_to' => $this->assignedTo,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'resolved_at' => $this->resolvedAt,
        ];
    }
}
