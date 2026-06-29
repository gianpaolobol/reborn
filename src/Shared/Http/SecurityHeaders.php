<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class SecurityHeaders
{
    /** @param array<string, mixed> $config */
    public static function apply(array $config = []): void
    {
        if (($config['security_headers_enabled'] ?? true) === false) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Resource-Policy: same-origin');
    }

    /** @param array<string, mixed> $config */
    public static function applyApi(array $config = []): void
    {
        self::apply($config);
        header('Cache-Control: no-store, max-age=0');
        header('X-Reborn-Security: production-readiness-v1');
    }
}
