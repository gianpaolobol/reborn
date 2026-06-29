<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Reborn\Shared\Database\Connection;
use Reborn\Shared\Database\MigrationRunner;
use Reborn\Shared\Support\Env;

$root = dirname(__DIR__);
$envPath = $root . '/.env';

if (!is_file($envPath)) {
    copy($root . '/.env.example', $envPath);
    echo "Created .env from .env.example\n";
}

Env::load($envPath);
$config = require $root . '/config/database.php';
$pdo = (new Connection($config))->pdo();

(new MigrationRunner($pdo, $root . '/database/migrations'))->run();

echo "Migrations executed.\n";

foreach (glob($root . '/database/seeds/*.sql') ?: [] as $seedFile) {
    $sql = file_get_contents($seedFile);
    if ($sql !== false) {
        $pdo->exec($sql);
        echo "Seeded " . basename($seedFile) . "\n";
    }
}

echo "Development database ready: " . $config['database'] . "\n";
