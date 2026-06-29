<?php

declare(strict_types=1);

namespace Reborn\Identity\Application;

use Reborn\Identity\Domain\AuthSession;
use Reborn\Identity\Domain\User;

final class AuthResult
{
    public function __construct(
        public readonly User $user,
        public readonly AuthSession $session,
        public readonly string $plainTextToken,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'token' => [
                'type' => 'Bearer',
                'access_token' => $this->plainTextToken,
                'expires_at' => $this->session->expiresAt,
            ],
        ];
    }
}
