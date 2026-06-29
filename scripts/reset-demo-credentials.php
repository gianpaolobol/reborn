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

$requiredColumns = ['id', 'email', 'name', 'role', 'password_hash', 'status', 'email_verified_at', 'created_at', 'updated_at'];
$columns = [];
foreach ($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[] = (string) $column['name'];
}

$missing = array_values(array_diff($requiredColumns, $columns));
if ($missing !== []) {
    fwrite(STDERR, 'Users table is missing required columns: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$demoUsers = [
    [
        'id' => 'user-demo-repair',
        'email' => 'repair.user@reborn.local',
        'name' => 'Demo Repair User',
        'role' => 'repair_user',
    ],
    [
        'id' => 'user-demo-maker',
        'email' => 'maker@reborn.local',
        'name' => 'Demo Maker',
        'role' => 'maker',
    ],
    [
        'id' => 'user-demo-provider',
        'email' => 'provider@reborn.local',
        'name' => 'Demo Provider',
        'role' => 'provider',
    ],
    [
        'id' => 'user-demo-enterprise',
        'email' => 'enterprise@reborn.local',
        'name' => 'Demo Enterprise',
        'role' => 'enterprise',
    ],
    [
        'id' => 'user-demo-admin',
        'email' => 'admin@reborn.local',
        'name' => 'Demo Admin',
        'role' => 'admin',
    ],
];

$now = gmdate('c');
$passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 10]);

$pdo->beginTransaction();
try {
    foreach ($demoUsers as $user) {
        $email = strtolower($user['email']);
        $existing = $pdo->prepare('SELECT id FROM users WHERE lower(email) = lower(:email) LIMIT 1');
        $existing->execute(['email' => $email]);
        $existingId = $existing->fetchColumn();

        if (is_string($existingId) && $existingId !== '') {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET name = :name,
                     role = :role,
                     password_hash = :password_hash,
                     status = :status,
                     email_verified_at = COALESCE(email_verified_at, :email_verified_at),
                     updated_at = :updated_at
                 WHERE lower(email) = lower(:email)'
            );
            $stmt->execute([
                'email' => $email,
                'name' => $user['name'],
                'role' => $user['role'],
                'password_hash' => $passwordHash,
                'status' => 'active',
                'email_verified_at' => $now,
                'updated_at' => $now,
            ]);
            continue;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (id, email, name, role, password_hash, status, email_verified_at, created_at, updated_at)
             VALUES (:id, :email, :name, :role, :password_hash, :status, :email_verified_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            'id' => $user['id'],
            'email' => $email,
            'name' => $user['name'],
            'role' => $user['role'],
            'password_hash' => $passwordHash,
            'status' => 'active',
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

$verified = [];
$stmt = $pdo->prepare('SELECT email, role, password_hash, status FROM users WHERE lower(email) = lower(:email) LIMIT 1');
foreach ($demoUsers as $user) {
    $stmt->execute(['email' => $user['email']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException($user['email'] . ' was not persisted.');
    }
    if (($row['role'] ?? null) !== $user['role']) {
        throw new RuntimeException($user['email'] . ' role mismatch after reset.');
    }
    if (($row['status'] ?? null) !== 'active') {
        throw new RuntimeException($user['email'] . ' status mismatch after reset.');
    }
    if (!password_verify('password', (string) $row['password_hash'])) {
        throw new RuntimeException($user['email'] . ' password verification failed after reset.');
    }
    $verified[] = $user['email'];
}

$result = [
    'success' => true,
    'reset_at' => $now,
    'database' => $config['database'] ?? null,
    'verified_users' => $verified,
    'password_hash_algorithm' => password_get_info($passwordHash)['algoName'] ?? 'unknown',
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
