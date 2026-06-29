<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'name' => 'Re-born',
    'mission' => 'Allow anyone to repair anything.',
    'env' => Env::get('APP_ENV', 'development'),
    'debug' => Env::bool('APP_DEBUG', true),
    'url' => Env::get('APP_URL', 'http://127.0.0.1:8080'),
    'api_version' => Env::get('API_VERSION', 'v1'),
];
