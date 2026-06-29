<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'token_ttl_seconds' => (int) Env::get('AUTH_TOKEN_TTL_SECONDS', 604800),
    'default_role' => Env::get('AUTH_DEFAULT_ROLE', 'repair_user'),
    'public_registration_roles' => ['repair_user', 'maker', 'provider'],
    'roles' => [
        'repair_user',
        'maker',
        'provider',
        'enterprise',
        'admin',
    ],
];
