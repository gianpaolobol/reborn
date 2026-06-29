<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct(404, 'NOT_FOUND', $message);
    }
}
