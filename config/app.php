<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'name' => 'Re-born',
    'mission' => 'Allow anyone to repair anything.',
    'env' => Env::get('APP_ENV', 'development'),
    // Debug output is never on by default in production: it would leak stack traces and paths.
    'debug' => Env::bool('APP_DEBUG', strtolower((string) Env::get('APP_ENV', 'development')) !== 'production'),
    'url' => Env::get('APP_URL', 'http://127.0.0.1:8080'),
    'api_version' => Env::get('API_VERSION', 'v1'),
];
