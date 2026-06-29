<?php

declare(strict_types=1);

namespace Reborn\Identity\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class UserRegistered implements DomainEvent
{
    public function __construct(
        private readonly User $user,
        private readonly ?string $occurredAt = null,
    ) {
    }

    public function name(): string
    {
        return 'identity.user_registered';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'role' => $this->user->role,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt ?? gmdate('c');
    }
}