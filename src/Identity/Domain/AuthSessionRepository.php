<?php

declare(strict_types=1);

namespace Reborn\Identity\Domain;

interface AuthSessionRepository
{
    /** @param list<string> $abilities */
    public function create(string $userId, string $tokenHash, string $name, array $abilities, ?string $ipAddress, ?string $userAgent, string $expiresAt): AuthSession;

    public function findActiveByTokenHash(string $tokenHash): ?AuthSession;

    public function touchLastUsed(string $id): void;

    public function revokeByTokenHash(string $tokenHash): void;
}
