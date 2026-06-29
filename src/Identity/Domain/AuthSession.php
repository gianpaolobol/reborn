<?php

declare(strict_types=1);

namespace Reborn\Identity\Domain;

final class AuthSession
{
    /**
     * @param list<string> $abilities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tokenHash,
        public readonly string $name,
        public readonly array $abilities,
        public readonly string $expiresAt,
        public readonly ?string $revokedAt,
        public readonly string $createdAt,
        public readonly ?string $lastUsedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $abilities = json_decode((string) ($row['abilities'] ?? '[]'), true);
        return new self(
            (string) $row['id'],
            (string) $row['user_id'],
            (string) $row['token_hash'],
            (string) ($row['name'] ?? 'api_session'),
            is_array($abilities) ? array_values(array_map('strval', $abilities)) : [],
            (string) $row['expires_at'],
            $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
            (string) $row['created_at'],
            $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
        );
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null && strtotime($this->expiresAt) > time();
    }
}
