<?php

declare(strict_types=1);

namespace Reborn\Shared\Support;

final class Validator
{
    /** @param array<string, mixed> $data @param list<string> $required @return array<string, list<string>> */
    public static function required(array $data, array $required): array
    {
        $errors = [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $errors[$field][] = $field . ' is required.';
            }
        }

        return $errors;
    }
}
