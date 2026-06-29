<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class ForbiddenException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'Insufficient permissions.', array $details = [])
    {
        parent::__construct(403, 'FORBIDDEN', $message, $details);
    }
}
