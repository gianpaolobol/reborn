<?php

declare(strict_types=1);

namespace Reborn\Identity\Infrastructure;

use PDO;
use Reborn\Identity\Domain\User;
use Reborn\Identity\Domain\UserRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE lower(email) = lower(:email) LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? User::fromRow($row) : null;
    }

    public function passwordHashForEmail(string $email): ?string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE lower(email) = lower(:email) LIMIT 1');
        $stmt->execute(['email' => $email]);
        $hash = $stmt->fetchColumn();

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        $now = gmdate('c');
        $id = (string) ($data['id'] ?? Uuid::v4());

        $stmt = $this->pdo->prepare('INSERT INTO users (id, email, name, role, password_hash, status, email_verified_at, created_at, updated_at, last_login_at) VALUES (:id, :email, :name, :role, :password_hash, :status, :email_verified_at, :created_at, :updated_at, :last_login_at)');
        $stmt->execute([
            'id' => $id,
            'email' => strtolower(trim((string) $data['email'])),
            'name' => trim((string) $data['name']),
            'role' => (string) $data['role'],
            'password_hash' => (string) $data['password_hash'],
            'status' => (string) ($data['status'] ?? 'active'),
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => null,
        ]);

        $user = $this->findById($id);
        if ($user === null) {
            throw new \RuntimeException('User creation failed.');
        }

        return $user;
    }

    public function touchLastLogin(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'last_login_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ]);
    }
}
