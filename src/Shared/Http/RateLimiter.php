<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

use PDO;
use PDOException;
use Reborn\Shared\Support\Uuid;

final class RateLimiter
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
    }

    public function enforce(Request $request): ?JsonResponse
    {
        if (($this->config['rate_limit_enabled'] ?? true) === false) {
            return null;
        }

        if (!str_starts_with($request->path(), '/api/')) {
            return null;
        }

        if (in_array($request->path(), $this->config['rate_limit_excluded_paths'] ?? [], true)) {
            return null;
        }

        $limit = max(1, (int) ($this->config['rate_limit_max_requests'] ?? 240));
        $windowSeconds = max(1, (int) ($this->config['rate_limit_window_seconds'] ?? 60));
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        $key = $this->rateKey($request);
        $route = $request->path();

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT id, request_count FROM api_rate_limits WHERE rate_key = :rate_key AND route = :route AND window_start = :window_start LIMIT 1');
            $stmt->execute([
                'rate_key' => $key,
                'route' => $route,
                'window_start' => $windowStart,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $row ? ((int) $row['request_count']) + 1 : 1;

            if ($row) {
                $update = $this->pdo->prepare('UPDATE api_rate_limits SET request_count = :request_count, last_seen_at = :last_seen_at WHERE id = :id');
                $update->execute([
                    'request_count' => $count,
                    'last_seen_at' => gmdate('c'),
                    'id' => $row['id'],
                ]);
            } else {
                $insert = $this->pdo->prepare('INSERT INTO api_rate_limits (id, rate_key, route, window_start, request_count, last_seen_at, created_at) VALUES (:id, :rate_key, :route, :window_start, :request_count, :last_seen_at, :created_at)');
                $insert->execute([
                    'id' => Uuid::v4(),
                    'rate_key' => $key,
                    'route' => $route,
                    'window_start' => $windowStart,
                    'request_count' => $count,
                    'last_seen_at' => gmdate('c'),
                    'created_at' => gmdate('c'),
                ]);
            }
            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Fail-open in MVP: readiness should report database issues separately.
            return null;
        }

        if ($count <= $limit) {
            return null;
        }

        $retryAfter = max(1, ($windowStart + $windowSeconds) - $now);
        return JsonResponse::error('RATE_LIMITED', 'Too many API requests for this window.', 429, [
            'limit' => $limit,
            'window_seconds' => $windowSeconds,
            'retry_after_seconds' => $retryAfter,
        ], $request->requestId());
    }

    private function rateKey(Request $request): string
    {
        $token = $request->bearerToken();
        if ($token !== null) {
            return 'token:' . hash('sha256', $token);
        }

        return 'ip:' . ($request->ipAddress() ?: 'unknown');
    }
}
