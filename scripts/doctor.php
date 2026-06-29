<?php

declare(strict_types=1);

$checks = [
    'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO available' => extension_loaded('pdo'),
    'PDO SQLite available' => extension_loaded('pdo_sqlite'),
    'JSON available' => extension_loaded('json'),
    'public/prototype exists' => is_file(dirname(__DIR__) . '/public/prototype/index.html'),
    '.env.example exists' => is_file(dirname(__DIR__) . '/.env.example'),
];

foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if (in_array(false, $checks, true)) {
    exit(1);
}
