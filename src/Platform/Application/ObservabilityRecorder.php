<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Throwable;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Support\Uuid;

final class ObservabilityRecorder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(Request $request, int $statusCode, int $durationMs): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO platform_http_metrics (id, request_id, method, path, status_code, duration_ms, ip_address, user_agent, occurred_at) VALUES (:id, :request_id, :method, :path, :status_code, :duration_ms, :ip_address, :user_agent, :occurred_at)');
            $stmt->execute([
                'id' => Uuid::v4(),
                'request_id' => $request->requestId(),
                'method' => $request->method(),
                'path' => $this->normalizePath($request->path()),
                'status_code' => $statusCode,
                'duration_ms' => max(0, $durationMs),
                'ip_address' => $request->ipAddress(),
                'user_agent' => $this->truncate($request->userAgent(), 255),
                'occurred_at' => gmdate('c'),
            ]);
        } catch (Throwable) {
            // Observability must never break the Repair Journey. Missing migrations or DB issues are surfaced by readiness.
        }
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim($path, '/') ?: '/';
        $path = preg_replace('#/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#i', '/{id}', $path) ?? $path;
        $path = preg_replace('#/[A-Za-z0-9_-]{18,}#', '/{id}', $path) ?? $path;
        return $this->truncate($path, 180) ?? $path;
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }
}
