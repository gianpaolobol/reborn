<?php

declare(strict_types=1);

$requiredExtensions = ['json', 'pdo', 'pdo_sqlite', 'sqlite3'];
$missing = [];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $missing[] = $extension;
    }
}

echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "SAPI: " . PHP_SAPI . PHP_EOL;
echo "Project root: " . dirname(__DIR__) . PHP_EOL;

if ($missing !== []) {
    echo "Missing extensions: " . implode(', ', $missing) . PHP_EOL;
    echo "Install/enable them before running the backend. On Windows check php.ini and restart PowerShell." . PHP_EOL;
    exit(1);
}

foreach (['storage/database', 'storage/logs', 'storage/app/uploads'] as $relativePath) {
    $path = dirname(__DIR__) . '/' . $relativePath;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }

    echo "Writable check {$relativePath}: " . (is_writable($path) ? 'ok' : 'not writable') . PHP_EOL;
}

echo "Doctor check passed.\n";
