<?php

declare(strict_types=1);

namespace Reborn\Governance\Domain;

final class ProviderGovernanceAction
{
    public function __construct(
        public readonly string $id,
        public readonly string $providerId,
        public readonly string $actionType,
        public readonly string $severity,
        public readonly string $status,
        public readonly string $reason,
        public readonly ?string $notes,
        public readonly float $scoreAdjustment,
        public readonly ?string $expiresAt,
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
            (string) $row['provider_id'],
            (string) $row['action_type'],
            (string) $row['severity'],
            (string) $row['status'],
            (string) $row['reason'],
            $row['notes'] !== null ? (string) $row['notes'] : null,
            (float) $row['score_adjustment'],
            $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
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
            'provider_id' => $this->providerId,
            'action_type' => $this->actionType,
            'severity' => $this->severity,
            'status' => $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'score_adjustment' => $this->scoreAdjustment,
            'expires_at' => $this->expiresAt,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'resolved_at' => $this->resolvedAt,
        ];
    }
}
