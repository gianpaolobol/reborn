<?php

declare(strict_types=1);

namespace Reborn\Identity\Domain;

final class User
{
    public const ROLE_REPAIR_USER = 'repair_user';
    public const ROLE_MAKER = 'maker';
    public const ROLE_PROVIDER = 'provider';
    public const ROLE_ENTERPRISE = 'enterprise';
    public const ROLE_ADMIN = 'admin';

    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
        public readonly string $role,
        public readonly string $status,
        public readonly ?string $emailVerifiedAt,
        public readonly string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $lastLoginAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['email'],
            (string) $row['name'],
            (string) $row['role'],
            (string) ($row['status'] ?? 'active'),
            $row['email_verified_at'] !== null ? (string) $row['email_verified_at'] : null,
            (string) $row['created_at'],
            $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
            $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @param list<string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'status' => $this->status,
            'email_verified_at' => $this->emailVerifiedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'last_login_at' => $this->lastLoginAt,
        ];
    }
}
