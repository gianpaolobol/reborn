<?php

declare(strict_types=1);

namespace Reborn\Identity\Application;

use Reborn\Identity\Domain\AuthSessionRepository;
use Reborn\Identity\Domain\UserLoggedIn;
use Reborn\Identity\Domain\UserRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\UnauthorizedException;
use Reborn\Shared\Http\ValidationException;

final class LoginUserService
{
    /** @param array<string, mixed> $authConfig */
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthSessionRepository $sessions,
        private readonly PasswordHasher $passwords,
        private readonly TokenFactory $tokens,
        private readonly EventBus $events,
        private readonly array $authConfig,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function handle(array $data, ?string $ipAddress, ?string $userAgent): AuthResult
    {
        $errors = [];
        foreach (['email', 'password'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field][] = $field . ' is required.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $email = strtolower(trim((string) $data['email']));
        $user = $this->users->findByEmail($email);
        $hash = $this->users->passwordHashForEmail($email);

        if ($user === null || $hash === null || !$this->passwords->verify((string) $data['password'], $hash)) {
            throw new UnauthorizedException('Invalid email or password.');
        }

        if (!$user->isActive()) {
            throw new UnauthorizedException('User account is not active.');
        }

        $plainTextToken = $this->tokens->plainTextToken();
        $expiresAt = gmdate('c', time() + (int) $this->authConfig['token_ttl_seconds']);
        $session = $this->sessions->create($user->id, $this->tokens->hash($plainTextToken), 'login', ['*'], $ipAddress, $userAgent, $expiresAt);
        $this->users->touchLastLogin($user->id);
        $freshUser = $this->users->findById($user->id) ?? $user;
        $this->events->publish(new UserLoggedIn($freshUser, $session->id));

        return new AuthResult($freshUser, $session, $plainTextToken);
    }
}
