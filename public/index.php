<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var Reborn\Shared\Http\Router $router */
$router = $app['router'];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/' || $path === '') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Re-born API</title><style>body{font-family:Inter,Arial,sans-serif;background:#f7f5ef;color:#171717;margin:0;padding:48px;line-height:1.5}.card{max-width:760px;border:1px solid #d8d3c8;background:white;padding:32px;border-radius:4px}code{background:#f1eee7;padding:2px 6px;border-radius:4px}a{color:#0657ff}</style></head><body><main class="card"><h1>Re-born Repair Intelligence API</h1><p><strong>Mission:</strong> Allow anyone to repair anything.</p><p>API health: <a href="/api/health">/api/health</a></p><p>Static prototype: <a href="/prototype/index.html">/prototype/index.html</a></p><p>Run seed setup with <code>php scripts/setup-dev.php</code>.</p></main></body></html>';
    return;
}

$router->dispatch()->send();
