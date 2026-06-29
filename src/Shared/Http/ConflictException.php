<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class ConflictException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'Resource conflict.', array $details = [])
    {
        parent::__construct(409, 'CONFLICT', $message, $details);
    }
}
