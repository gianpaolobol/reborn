<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

$databasePath = Env::get('DB_DATABASE', 'storage/database/reborn.sqlite');

if (!str_starts_with($databasePath, '/') && !preg_match('/^[A-Z]:\\\\/i', $databasePath)) {
    $databasePath = dirname(__DIR__) . '/' . $databasePath;
}

return [
    'connection' => Env::get('DB_CONNECTION', 'sqlite'),
    'database' => $databasePath,
    'maria_host' => Env::get('DB_HOST', '127.0.0.1'),
    'maria_port' => Env::get('DB_PORT', '3306'),
    'maria_database' => Env::get('DB_NAME', 'reborn'),
    'maria_user' => Env::get('DB_USER', 'reborn'),
    'maria_password' => Env::get('DB_PASSWORD', ''),
];
