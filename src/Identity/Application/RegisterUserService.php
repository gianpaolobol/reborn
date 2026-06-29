<?php

declare(strict_types=1);

namespace Reborn\Identity\Application;

use Reborn\Identity\Domain\AuthSessionRepository;
use Reborn\Identity\Domain\UserRegistered;
use Reborn\Identity\Domain\UserRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\ConflictException;
use Reborn\Shared\Http\ValidationException;

final class RegisterUserService
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
        $errors = $this->validate($data);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $email = strtolower(trim((string) $data['email']));
        if ($this->users->findByEmail($email) !== null) {
            throw new ConflictException('A user with this email already exists.', ['email' => $email]);
        }

        $role = (string) ($data['role'] ?? $this->authConfig['default_role']);
        $user = $this->users->create([
            'email' => $email,
            'name' => trim((string) $data['name']),
            'role' => $role,
            'password_hash' => $this->passwords->hash((string) $data['password']),
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $result = $this->issueToken($user->id, 'registration', $ipAddress, $userAgent);
        $this->events->publish(new UserRegistered($user));

        return new AuthResult($user, $result['session'], $result['token']);
    }

    /** @return array<string, list<string>> */
    private function validate(array $data): array
    {
        $errors = [];
        foreach (['name', 'email', 'password'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field][] = $field . ' is required.';
            }
        }

        $email = (string) ($data['email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'email must be valid.';
        }

        $password = (string) ($data['password'] ?? '');
        if ($password !== '' && strlen($password) < 8) {
            $errors['password'][] = 'password must be at least 8 characters.';
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '' && strlen($name) > 120) {
            $errors['name'][] = 'name must be no more than 120 characters.';
        }

        $role = (string) ($data['role'] ?? $this->authConfig['default_role']);
        $allowed = $this->authConfig['public_registration_roles'] ?? ['repair_user'];
        if (!in_array($role, $allowed, true)) {
            $errors['role'][] = 'role is not available for public registration.';
        }

        return $errors;
    }

    /** @return array{session:\Reborn\Identity\Domain\AuthSession, token:string} */
    private function issueToken(string $userId, string $name, ?string $ipAddress, ?string $userAgent): array
    {
        $plainTextToken = $this->tokens->plainTextToken();
        $expiresAt = gmdate('c', time() + (int) $this->authConfig['token_ttl_seconds']);
        $session = $this->sessions->create($userId, $this->tokens->hash($plainTextToken), $name, ['*'], $ipAddress, $userAgent, $expiresAt);

        return ['session' => $session, 'token' => $plainTextToken];
    }
}
