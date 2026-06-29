<?php

declare(strict_types=1);

namespace Reborn\Identity\Domain;

interface UserRepository
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function passwordHashForEmail(string $email): ?string;

    /** @param array<string, mixed> $data */
    public function create(array $data): User;

    public function touchLastLogin(string $id): void;
}
