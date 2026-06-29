<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class ValidationException extends ApiException
{
    /** @param array<string, list<string>> $fieldErrors */
    public function __construct(array $fieldErrors, string $message = 'The request contains invalid or missing fields.')
    {
        parent::__construct(422, 'VALIDATION_ERROR', $message, ['fields' => $fieldErrors]);
    }
}
