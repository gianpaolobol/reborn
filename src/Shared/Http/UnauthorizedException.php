<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class UnauthorizedException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'Authentication required.', array $details = [])
    {
        parent::__construct(401, 'UNAUTHORIZED', $message, $details);
    }
}
