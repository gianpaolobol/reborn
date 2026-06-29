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
}

Env::load($envPath);
$config = require $root . '/config/database.php';
$pdo = (new Connection($config))->pdo();
(new MigrationRunner($pdo, $root . '/database/migrations'))->run();

$checks = [
    'repair_cases table' => "SELECT name FROM sqlite_master WHERE type='table' AND name='repair_cases'",
    'repair_attachments table' => "SELECT name FROM sqlite_master WHERE type='table' AND name='repair_attachments'",
    'domain_events table' => "SELECT name FROM sqlite_master WHERE type='table' AND name='domain_events'",
    'audit_log table' => "SELECT name FROM sqlite_master WHERE type='table' AND name='audit_log'",
];

$failed = 0;
foreach ($checks as $label => $sql) {
    $result = $pdo->query($sql)->fetchColumn();
    if (!$result) {
        echo "[FAIL] {$label}\n";
        $failed++;
        continue;
    }

    echo "[OK] {$label}\n";
}

if ($failed > 0) {
    exit(1);
}

echo "Feature checks passed.\n";
