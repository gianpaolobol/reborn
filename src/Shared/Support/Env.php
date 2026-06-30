<?php

declare(strict_types=1);

namespace Reborn\Shared\Support;

final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? 'true' : 'false');
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /** @return list<string> */
    public static function csv(string $key, array $default = []): array
    {
        $value = self::get($key, null);
        if ($value === null || $value === false || trim((string) $value) === '') {
            return array_values(array_map('strval', $default));
        }

        return array_values(array_filter(array_map(
            static fn(string $item): string => trim($item),
            explode(',', (string) $value)
        ), static fn(string $item): bool => $item !== ''));
    }
}

