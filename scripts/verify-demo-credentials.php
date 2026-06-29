<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Reborn\Shared\Database\Connection;
use Reborn\Shared\Support\Env;

$root = dirname(__DIR__);
$envPath = $root . '/.env';

if (!is_file($envPath)) {
    fwrite(STDERR, "Missing .env. Run php scripts/setup-dev.php first.\n");
    exit(1);
}

Env::load($envPath);
$config = require $root . '/config/database.php';
$pdo = (new Connection($config))->pdo();

$expectedUsers = [
    'repair.user@reborn.local' => 'repair_user',
    'maker@reborn.local' => 'maker',
    'provider@reborn.local' => 'provider',
    'enterprise@reborn.local' => 'enterprise',
    'admin@reborn.local' => 'admin',
];

$failures = [];
$verified = [];

$stmt = $pdo->prepare('SELECT email, role, password_hash, status FROM users WHERE lower(email) = lower(:email) LIMIT 1');

foreach ($expectedUsers as $email => $role) {
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        $failures[] = "$email is missing from the demo seed.";
        continue;
    }

    if (($row['role'] ?? null) !== $role) {
        $failures[] = "$email has role " . ($row['role'] ?? 'null') . ", expected $role.";
    }

    if (($row['status'] ?? null) !== 'active') {
        $failures[] = "$email has status " . ($row['status'] ?? 'null') . ", expected active.";
    }

    $hash = (string) ($row['password_hash'] ?? '');
    if ($hash === '' || !password_verify('password', $hash)) {
        $failures[] = "$email does not verify against the expected demo password.";
        continue;
    }

    $verified[] = $email;
}

$result = [
    'success' => $failures === [],
    'checked_at' => gmdate('c'),
    'database' => $config['database'] ?? null,
    'verified_users' => $verified,
    'failures' => $failures,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($failures !== []) {
    exit(1);
}
