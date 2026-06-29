<?php

declare(strict_types=1);

namespace Reborn\Identity\Infrastructure;

use PDO;
use Reborn\Identity\Domain\AuthSession;
use Reborn\Identity\Domain\AuthSessionRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteAuthSessionRepository implements AuthSessionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param list<string> $abilities */
    public function create(string $userId, string $tokenHash, string $name, array $abilities, ?string $ipAddress, ?string $userAgent, string $expiresAt): AuthSession
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO auth_sessions (id, user_id, token_hash, name, abilities, ip_address, user_agent, expires_at, revoked_at, created_at, last_used_at) VALUES (:id, :user_id, :token_hash, :name, :abilities, :ip_address, :user_agent, :expires_at, NULL, :created_at, NULL)');
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'name' => $name,
            'abilities' => json_encode(array_values($abilities), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $session = $this->findById($id);
        if ($session === null) {
            throw new \RuntimeException('Auth session creation failed.');
        }

        return $session;
    }

    public function findActiveByTokenHash(string $tokenHash): ?AuthSession
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_sessions WHERE token_hash = :token_hash AND revoked_at IS NULL AND expires_at > :now LIMIT 1');
        $stmt->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('c'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? AuthSession::fromRow($row) : null;
    }

    public function touchLastUsed(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE auth_sessions SET last_used_at = :last_used_at WHERE id = :id');
        $stmt->execute(['id' => $id, 'last_used_at' => gmdate('c')]);
    }

    public function revokeByTokenHash(string $tokenHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE auth_sessions SET revoked_at = :revoked_at WHERE token_hash = :token_hash AND revoked_at IS NULL');
        $stmt->execute([
            'token_hash' => $tokenHash,
            'revoked_at' => gmdate('c'),
        ]);
    }

    private function findById(string $id): ?AuthSession
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? AuthSession::fromRow($row) : null;
    }
}
