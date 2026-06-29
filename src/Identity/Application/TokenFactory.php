<?php

declare(strict_types=1);

namespace Reborn\Identity\Application;

final class TokenFactory
{
    public function plainTextToken(): string
    {
        return 'rbn_' . bin2hex(random_bytes(32));
    }

    public function hash(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }
}
