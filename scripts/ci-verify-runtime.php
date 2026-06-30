<?php

declare(strict_types=1);

$required = ['pdo', 'pdo_sqlite', 'sqlite3', 'fileinfo', 'json'];
$missing = [];

foreach ($required as $extension) {
    if (!extension_loaded($extension)) {
        $missing[] = $extension;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Missing required PHP extensions: " . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE smoke_test (id INTEGER PRIMARY KEY, name TEXT)');
    $statement = $pdo->prepare('INSERT INTO smoke_test (name) VALUES (:name)');
    $statement->execute(['name' => 'runtime-ok']);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM smoke_test')->fetchColumn();

    if ($count !== 1) {
        fwrite(STDERR, "SQLite PDO memory database verification failed." . PHP_EOL);
        exit(1);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "SQLite PDO runtime check failed: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$payload = [
    'success' => true,
    'marker' => 'STEP38_RUNTIME_SCRIPT_VERIFY_V4',
    'php_version' => PHP_VERSION,
    'extensions' => array_values(array_filter($required, static fn (string $extension): bool => extension_loaded($extension))),
    'sqlite_pdo_memory' => 'ok',
];

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
