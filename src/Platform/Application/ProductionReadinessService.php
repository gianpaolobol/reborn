<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Throwable;
use Reborn\Shared\Support\Uuid;

final class ProductionReadinessService
{
    /** @param array<string, mixed> $appConfig @param array<string, mixed> $databaseConfig @param array<string, mixed> $securityConfig */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $appConfig,
        private readonly array $databaseConfig,
        private readonly array $securityConfig,
        private readonly string $rootPath,
    ) {
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'migrations' => $this->migrationsCheck(),
            'storage' => $this->storageCheck(),
            'configuration' => $this->configurationCheck(),
            'security' => $this->securityCheck(),
            'runtime' => $this->runtimeCheck(),
            'observability' => $this->observabilityCheck(),
            'backup' => $this->backupCheck(),
        ];

        $status = 'ready';
        foreach ($checks as $check) {
            if (($check['status'] ?? 'fail') === 'fail') {
                $status = 'not_ready';
                break;
            }
            if (($check['status'] ?? 'ok') === 'warn' && $status !== 'not_ready') {
                $status = 'degraded';
            }
        }

        return [
            'status' => $status,
            'environment' => $this->appConfig['env'] ?? 'unknown',
            'generated_at' => gmdate('c'),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    public function securityPolicy(): array
    {
        return [
            'policy_version' => 'production_readiness_v1',
            'security_headers_enabled' => (bool) ($this->securityConfig['security_headers_enabled'] ?? true),
            'rate_limit_enabled' => (bool) ($this->securityConfig['rate_limit_enabled'] ?? true),
            'rate_limit_max_requests' => (int) ($this->securityConfig['rate_limit_max_requests'] ?? 240),
            'rate_limit_window_seconds' => (int) ($this->securityConfig['rate_limit_window_seconds'] ?? 60),
            'max_upload_bytes' => (int) ($this->securityConfig['max_upload_bytes'] ?? 15728640),
            'headers' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'no-referrer',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
                'Cross-Origin-Resource-Policy' => 'same-origin',
                'Cache-Control' => 'no-store for API JSON responses',
            ],
            'notes' => [
                'MVP uses SQLite-backed fixed-window rate limiting for local and pilot readiness.',
                'CSP for the prototype remains intentionally deferred because the current static prototype uses inline handlers.',
                'Real payment webhooks remain out of scope until mock PaymentIntent is replaced with provider integrations.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function deployChecklist(): array
    {
        return [
            'checklist_version' => 'production_readiness_v2_step21',
            'items' => $this->securityConfig['production_checklist'] ?? [],
            'blocked_until' => [
                'APP_DEBUG=false is verified in the target environment',
                'real payment providers have signed webhook verification',
                'backup and restore procedure has been tested',
                'privacy/legal liability terms for repair outcomes are approved',
            ],
            'step_21_status' => 'Observability dashboard, backup automation and deployment runbook v1 implemented.',
        ];
    }

    /** @return array<string, mixed> */
    public function runtimeReport(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'app_env' => $this->appConfig['env'] ?? 'unknown',
            'app_debug' => (bool) ($this->appConfig['debug'] ?? false),
            'database_connection' => $this->databaseConfig['connection'] ?? 'unknown',
            'database_path' => ($this->databaseConfig['connection'] ?? 'sqlite') === 'sqlite' ? $this->databaseConfig['database'] : null,
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'sqlite3' => extension_loaded('sqlite3'),
                'json' => extension_loaded('json'),
                'fileinfo' => extension_loaded('fileinfo'),
            ],
            'storage' => [
                'logs_dir' => $this->rootPath . '/storage/logs',
                'uploads_dir' => $this->rootPath . '/storage/uploads',
                'database_dir' => $this->rootPath . '/storage/database',
                'free_bytes' => @disk_free_space($this->rootPath) ?: null,
            ],
        ];
    }

    public function recordSnapshot(?string $createdBy): array
    {
        $readiness = $this->readiness();
        $snapshot = [
            'id' => Uuid::v4(),
            'status' => $readiness['status'],
            'checks_json' => $readiness['checks'],
            'created_by' => $createdBy,
            'created_at' => gmdate('c'),
        ];

        $stmt = $this->pdo->prepare('INSERT INTO platform_readiness_snapshots (id, status, checks_json, created_by, created_at) VALUES (:id, :status, :checks_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => $snapshot['id'],
            'status' => $snapshot['status'],
            'checks_json' => json_encode($snapshot['checks_json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $snapshot['created_by'],
            'created_at' => $snapshot['created_at'],
        ]);

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function databaseCheck(): array
    {
        try {
            $value = $this->pdo->query('SELECT 1')->fetchColumn();
            return ['status' => ((int) $value) === 1 ? 'ok' : 'fail', 'message' => 'Database connection responds.'];
        } catch (Throwable $exception) {
            return ['status' => 'fail', 'message' => 'Database connection failed.', 'error' => $exception->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function migrationsCheck(): array
    {
        try {
            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            $latest = $this->pdo->query('SELECT filename FROM migrations ORDER BY executed_at DESC, id DESC LIMIT 1')->fetchColumn();
            return [
                'status' => $count >= 15 ? 'ok' : 'warn',
                'executed_count' => $count,
                'latest' => $latest ?: null,
                'message' => $count >= 15 ? 'All MVP hardening and observability migrations are present.' : 'Some migrations may still need to run.',
            ];
        } catch (Throwable $exception) {
            return ['status' => 'fail', 'message' => 'Migration metadata is unavailable.', 'error' => $exception->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function storageCheck(): array
    {
        $paths = [
            'storage' => $this->rootPath . '/storage',
            'logs' => $this->rootPath . '/storage/logs',
            'uploads' => $this->rootPath . '/storage/uploads',
            'database' => $this->rootPath . '/storage/database',
        ];

        $results = [];
        $hasFailure = false;
        foreach ($paths as $name => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $ok = is_dir($path) && is_writable($path);
            $results[$name] = ['path' => $path, 'writable' => $ok];
            if (!$ok) {
                $hasFailure = true;
            }
        }

        return ['status' => $hasFailure ? 'fail' : 'ok', 'paths' => $results];
    }

    /** @return array<string, mixed> */
    private function configurationCheck(): array
    {
        $env = (string) ($this->appConfig['env'] ?? 'development');
        $debug = (bool) ($this->appConfig['debug'] ?? true);
        $warnings = [];
        if ($env === 'production' && $debug) {
            $warnings[] = 'APP_DEBUG must be false in production.';
        }
        if ($env !== 'production') {
            $warnings[] = 'APP_ENV is not production; this is acceptable for local smoke tests.';
        }

        return [
            'status' => ($env === 'production' && $debug) ? 'fail' : ($warnings ? 'warn' : 'ok'),
            'app_env' => $env,
            'app_debug' => $debug,
            'warnings' => $warnings,
        ];
    }

    /** @return array<string, mixed> */
    private function securityCheck(): array
    {
        return [
            'status' => ((bool) ($this->securityConfig['security_headers_enabled'] ?? true) && (bool) ($this->securityConfig['rate_limit_enabled'] ?? true)) ? 'ok' : 'warn',
            'security_headers_enabled' => (bool) ($this->securityConfig['security_headers_enabled'] ?? true),
            'rate_limit_enabled' => (bool) ($this->securityConfig['rate_limit_enabled'] ?? true),
            'rate_limit_max_requests' => (int) ($this->securityConfig['rate_limit_max_requests'] ?? 240),
            'rate_limit_window_seconds' => (int) ($this->securityConfig['rate_limit_window_seconds'] ?? 60),
        ];
    }

    /** @return array<string, mixed> */
    private function observabilityCheck(): array
    {
        try {
            $table = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'platform_http_metrics'")->fetchColumn();
            if (!$table) {
                return ['status' => 'warn', 'message' => 'HTTP metrics table is not available yet. Run migrations.'];
            }

            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_http_metrics')->fetchColumn();
            $last = $this->pdo->query('SELECT occurred_at FROM platform_http_metrics ORDER BY occurred_at DESC LIMIT 1')->fetchColumn();
            return [
                'status' => 'ok',
                'message' => 'HTTP metrics recorder is available.',
                'recorded_requests' => $count,
                'last_request_at' => $last ?: null,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Observability metrics are not readable yet.', 'error' => $exception->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function backupCheck(): array
    {
        $backupDir = $this->rootPath . '/storage/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }

        $writable = is_dir($backupDir) && is_writable($backupDir);
        $latest = null;
        try {
            $latest = $this->pdo->query("SELECT created_at FROM platform_backup_runs WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1")->fetchColumn() ?: null;
        } catch (Throwable) {
            // Table may not exist before Step 21 migration. Keep this as a warning, not a hard failure.
        }

        if (!$writable) {
            return ['status' => 'fail', 'message' => 'Backup directory is not writable.', 'backup_dir' => $backupDir];
        }

        return [
            'status' => $latest ? 'ok' : 'warn',
            'message' => $latest ? 'At least one completed backup exists.' : 'Backup directory is writable, but no completed backup has been recorded yet.',
            'backup_dir' => $backupDir,
            'latest_completed_backup_at' => $latest,
        ];
    }

    /** @return array<string, mixed> */
    private function runtimeCheck(): array
    {
        $required = [
            'pdo' => extension_loaded('pdo'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'json' => extension_loaded('json'),
        ];
        $optional = [
            'sqlite3' => extension_loaded('sqlite3'),
            'fileinfo' => extension_loaded('fileinfo'),
        ];

        $missingRequired = array_keys(array_filter($required, static fn (bool $loaded): bool => !$loaded));
        $missingOptional = array_keys(array_filter($optional, static fn (bool $loaded): bool => !$loaded));

        return [
            'status' => $missingRequired ? 'fail' : ($missingOptional ? 'warn' : 'ok'),
            'php_version' => PHP_VERSION,
            'required_extensions' => $required,
            'optional_extensions' => $optional,
            'missing_required' => $missingRequired,
            'missing_optional' => $missingOptional,
            'message' => $missingRequired
                ? 'One or more required PHP extensions are missing.'
                : ($missingOptional ? 'Runtime is usable, but one or more optional extensions should be enabled before production.' : 'Runtime extensions are ready.'),
        ];
    }
}
