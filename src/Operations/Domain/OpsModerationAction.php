<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

final class OpsModerationAction
{
    public function __construct(
        public readonly string $id,
        public readonly string $reviewItemId,
        public readonly string $actionType,
        public readonly string $targetType,
        public readonly string $targetId,
        public readonly string $status,
        public readonly string $reason,
        public readonly array $payload,
        public readonly string $createdBy,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['review_item_id'],
            (string) $row['action_type'],
            (string) $row['target_type'],
            (string) $row['target_id'],
            (string) $row['status'],
            (string) $row['reason'],
            json_decode((string) ($row['payload_json'] ?? '{}'), true) ?: [],
            (string) $row['created_by'],
            (string) $row['created_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'review_item_id' => $this->reviewItemId,
            'action_type' => $this->actionType,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'status' => $this->status,
            'reason' => $this->reason,
            'payload' => $this->payload,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }
}
