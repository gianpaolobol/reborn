<?php

declare(strict_types=1);

namespace Reborn\Identity\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class UserLoggedIn implements DomainEvent
{
    public function __construct(private readonly User $user, private readonly string $sessionId)
    {
    }

    public function name(): string
    {
        return 'identity.user_logged_in';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->user->id,
            'role' => $this->user->role,
            'session_id' => $this->sessionId,
        ];
    }
}
