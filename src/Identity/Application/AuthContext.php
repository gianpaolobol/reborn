<?php

declare(strict_types=1);

namespace Reborn\Identity\Application;

use Reborn\Identity\Domain\AuthSession;
use Reborn\Identity\Domain\AuthSessionRepository;
use Reborn\Identity\Domain\User;
use Reborn\Identity\Domain\UserRepository;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Http\UnauthorizedException;

final class AuthContext
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthSessionRepository $sessions,
        private readonly TokenFactory $tokens,
    ) {
    }

    public function user(Request $request): User
    {
        [$user] = $this->authenticate($request);
        return $user;
    }

    /** @param list<string> $roles */
    public function requireRole(Request $request, array $roles): User
    {
        $user = $this->user($request);
        if (!$user->hasAnyRole($roles)) {
            throw new ForbiddenException('This endpoint requires one of these roles: ' . implode(', ', $roles) . '.', [
                'required_roles' => $roles,
                'current_role' => $user->role,
            ]);
        }

        return $user;
    }

    public function revokeCurrentSession(Request $request): void
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new UnauthorizedException('Missing bearer token.');
        }

        $this->sessions->revokeByTokenHash($this->tokens->hash($token));
    }

    /** @return array{User, AuthSession} */
    private function authenticate(Request $request): array
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new UnauthorizedException('Missing bearer token.');
        }

        $session = $this->sessions->findActiveByTokenHash($this->tokens->hash($token));
        if ($session === null) {
            throw new UnauthorizedException('Invalid or expired bearer token.');
        }

        $user = $this->users->findById($session->userId);
        if ($user === null || !$user->isActive()) {
            throw new UnauthorizedException('Authenticated user is not available.');
        }

        $this->sessions->touchLastUsed($session->id);

        return [$user, $session];
    }
}
