<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'security_headers_enabled' => Env::bool('SECURITY_HEADERS_ENABLED', true),
    'rate_limit_enabled' => Env::bool('RATE_LIMIT_ENABLED', true),
    'rate_limit_max_requests' => (int) Env::get('RATE_LIMIT_MAX_REQUESTS', 240),
    'rate_limit_window_seconds' => (int) Env::get('RATE_LIMIT_WINDOW_SECONDS', 60),
    'rate_limit_excluded_paths' => [
        '/api/health',
        '/api/ready',
        '/api/v1/platform/readiness',
        '/prototype/index.html',
    ],
    'max_upload_bytes' => (int) Env::get('MAX_UPLOAD_BYTES', 15728640),
    'trusted_proxy_headers' => Env::bool('TRUSTED_PROXY_HEADERS', false),
    'production_checklist' => [
        'APP_ENV=production before public launch',
        'APP_DEBUG=false before public launch',
        'HTTPS termination configured at the reverse proxy',
        'Database backups scheduled and restore tested',
        'Storage uploads excluded from git and backed up separately',
        'Payment provider webhooks signed before real payments',
        'Rate limit thresholds reviewed for pilot traffic',
        'Admin and ops accounts reviewed before beta',
    ],
];
