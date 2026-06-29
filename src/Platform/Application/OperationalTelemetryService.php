<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Throwable;

final class OperationalTelemetryService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ProductionReadinessService $readiness,
        private readonly BackupService $backups,
        private readonly string $rootPath,
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $readiness = $this->readiness->readiness();
        $http = $this->httpSummary();
        $logs = $this->logsSummary();
        $backup = $this->backups->status();

        return [
            'status' => $this->overallStatus($readiness['status'] ?? 'unknown', $http, $backup),
            'generated_at' => gmdate('c'),
            'readiness_status' => $readiness['status'] ?? 'unknown',
            'http' => $http,
            'logs' => $logs,
            'backup' => $backup,
            'storage' => $this->storageUsage(),
            'database' => $this->databaseOverview(),
            'next_operator_actions' => $this->nextOperatorActions($readiness, $backup),
        ];
    }

    /** @return array<string, mixed> */
    public function httpMetrics(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return [
            'summary' => $this->httpSummary(),
            'recent_requests' => $this->recentRequests($limit),
            'by_path' => $this->groupByPath(),
            'by_status' => $this->groupByStatus(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function readinessHistory(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $this->pdo->prepare('SELECT id, status, checks_json, created_by, created_at FROM platform_readiness_snapshots ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(static function (array $row): array {
                $row['checks'] = json_decode((string) ($row['checks_json'] ?? '{}'), true) ?: [];
                unset($row['checks_json']);
                return $row;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    public function logs(int $limit = 80): array
    {
        $limit = max(1, min(300, $limit));
        $logDir = $this->rootPath . '/storage/logs';
        $files = glob($logDir . '/*.log') ?: [];
        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        $entries = [];
        foreach (array_slice($files, 0, 5) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_slice(array_reverse($lines), 0, $limit) as $line) {
                $decoded = json_decode($line, true);
                $entries[] = is_array($decoded)
                    ? ['file' => basename($file)] + $decoded
                    : ['file' => basename($file), 'message' => $line];
                if (count($entries) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            'log_dir' => 'storage/logs',
            'files' => array_map(fn (string $file): array => [
                'name' => basename($file),
                'size_bytes' => (int) filesize($file),
                'modified_at' => gmdate('c', filemtime($file) ?: time()),
            ], $files),
            'entries' => $entries,
        ];
    }

    /** @return array<string, mixed> */
    public function deploymentRunbook(): array
    {
        return [
            'runbook_version' => 'deployment_runbook_v7_step26',
            'purpose' => 'Move Re-born from local MVP to controlled pilot without pretending it is enterprise-grade production yet.',
            'phases' => [
                [
                    'name' => '1. Local preflight',
                    'checks' => [
                        'git status is clean except intended Step 26 files',
                        'php scripts/setup-dev.php completes',
                        'all smoke tests pass from a second PowerShell window, including Step 26 release management',
                        'manual backup has been created through API or scripts/backup-sqlite.ps1',
                    ],
                ],
                [
                    'name' => '2. Pilot environment',
                    'checks' => [
                        'APP_ENV is set for the target environment',
                        'APP_DEBUG=false outside local development',
                        'storage/database, storage/logs, storage/uploads and storage/backups are writable',
                        'security headers and rate limiting are enabled',
                    ],
                ],
                [
                    'name' => '3. Data safety',
                    'checks' => [
                        'backup restore procedure has been rehearsed',
                        'storage/uploads retention policy is documented',
                        'operator can inspect API logs and readiness snapshots',
                        'operator can evaluate alerts and update the local/pilot status page',
                        'operator can dispatch Step 23 mock notifications and start escalation runs',
                        'operator can evaluate SLA governance, privacy retention dry-runs and release gates',
                    ],
                ],
                [
                    'name' => '4. Go / no-go',
                    'checks' => [
                        'readiness is ready or degraded only for accepted warnings',
                        'observability dashboard shows no repeated 500 errors',
                        'Step 22 alert evaluation has been run and no unresolved critical incident blocks the release',
                        'Step 23 notification dispatch has no failed mandatory deliveries',
                        'Step 24 SLA evaluation has no unreviewed breach blocking the pilot',
                        'Step 25 privacy governance has reviewed notices, retention dry-run and open DSR status',
                        'admin ops workflow still works after deploy',
                        'Step 26 release gates and pilot cohort rules have been reviewed',
                        'known mocks are explicitly disclosed in demo or pilot notes',
                    ],
                ],
            ],
            'rollback' => [
                'Stop the deployed process.',
                'Restore the last known-good SQLite backup.',
                'Revert the application release to the previous commit or ZIP.',
                'Run readiness and observability smoke tests again before reopening the pilot.',
            ],
            'not_yet_production_grade' => [
                'real AI diagnosis and model generation',
                'real payment provider webhooks',
                'final legal/privacy pack and real consent UX',
                'centralized logs and external uptime monitoring',
                'provider SLA and dispute workflow',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function smokeTestsSummary(): array
    {
        $scripts = [
            'smoke-identity-access.ps1' => 'Identity & access',
            'smoke-ownership-dashboards.ps1' => 'Ownership dashboards',
            'smoke-prototype-auth-ui.ps1' => 'Prototype auth UI',
            'smoke-repair-upload-recognition.ps1' => 'Upload & recognition',
            'smoke-repair-path-decision.ps1' => 'Repair path decision',
            'smoke-provider-match-quote.ps1' => 'Provider match & quote',
            'smoke-repair-order-payment-intent.ps1' => 'Order & payment intent',
            'smoke-repair-fulfilment-workflow.ps1' => 'Fulfilment workflow',
            'smoke-repair-completion-learning.ps1' => 'Completion & learning',
            'smoke-provider-trust-quality.ps1' => 'Trust & quality',
            'smoke-provider-ranking-governance.ps1' => 'Governance',
            'smoke-admin-ops-moderation.ps1' => 'Admin ops',
            'smoke-production-readiness.ps1' => 'Production readiness',
            'smoke-observability-ops.ps1' => 'Step 21 observability ops',
            'smoke-incident-response-status.ps1' => 'Step 22 incident response status',
            'smoke-notification-escalation.ps1' => 'Step 23 notification escalation',
            'smoke-service-governance-sla.ps1' => 'Step 24 service governance SLA',
            'smoke-privacy-data-governance.ps1' => 'Step 25 privacy data governance',
            'smoke-beta-release-management.ps1' => 'Step 26 beta release management',
        ];

        $rows = [];
        foreach ($scripts as $file => $label) {
            $path = $this->rootPath . '/scripts/' . $file;
            $rows[] = [
                'script' => 'scripts/' . $file,
                'label' => $label,
                'exists' => is_file($path),
            ];
        }

        return [
            'run_order' => $rows,
            'powershell_prefix' => 'powershell -ExecutionPolicy Bypass -File',
            'note' => 'Run with the PHP server already open in a separate PowerShell window.',
        ];
    }

    /** @return array<string, mixed> */
    private function httpSummary(): array
    {
        try {
            $row = $this->pdo->query('SELECT COUNT(*) AS total, SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS errors_5xx, SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) AS errors_4xx, ROUND(AVG(duration_ms), 2) AS avg_duration_ms, MAX(duration_ms) AS max_duration_ms FROM platform_http_metrics')->fetch(PDO::FETCH_ASSOC) ?: [];
            $last = $this->pdo->query('SELECT occurred_at FROM platform_http_metrics ORDER BY occurred_at DESC LIMIT 1')->fetchColumn();
            return [
                'total_requests' => (int) ($row['total'] ?? 0),
                'errors_5xx' => (int) ($row['errors_5xx'] ?? 0),
                'errors_4xx' => (int) ($row['errors_4xx'] ?? 0),
                'avg_duration_ms' => (float) ($row['avg_duration_ms'] ?? 0),
                'max_duration_ms' => (int) ($row['max_duration_ms'] ?? 0),
                'last_request_at' => $last ?: null,
            ];
        } catch (Throwable) {
            return [
                'total_requests' => 0,
                'errors_5xx' => 0,
                'errors_4xx' => 0,
                'avg_duration_ms' => 0,
                'max_duration_ms' => 0,
                'last_request_at' => null,
            ];
        }
    }

    /** @return list<array<string, mixed>> */
    private function recentRequests(int $limit): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT request_id, method, path, status_code, duration_ms, occurred_at FROM platform_http_metrics ORDER BY occurred_at DESC LIMIT :limit');
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(static function (array $row): array {
                $row['status_code'] = (int) $row['status_code'];
                $row['duration_ms'] = (int) $row['duration_ms'];
                return $row;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function groupByPath(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT path, COUNT(*) AS total, ROUND(AVG(duration_ms), 2) AS avg_duration_ms, SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS errors_5xx FROM platform_http_metrics GROUP BY path ORDER BY total DESC, path ASC LIMIT 20');
            return array_map(static fn (array $row): array => [
                'path' => $row['path'],
                'total' => (int) $row['total'],
                'avg_duration_ms' => (float) $row['avg_duration_ms'],
                'errors_5xx' => (int) $row['errors_5xx'],
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function groupByStatus(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT status_code, COUNT(*) AS total FROM platform_http_metrics GROUP BY status_code ORDER BY total DESC, status_code ASC');
            return array_map(static fn (array $row): array => [
                'status_code' => (int) $row['status_code'],
                'total' => (int) $row['total'],
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function logsSummary(): array
    {
        $logs = $this->logs(5);
        return [
            'files_count' => count($logs['files'] ?? []),
            'recent_entries_count' => count($logs['entries'] ?? []),
            'latest_file' => ($logs['files'][0]['name'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function storageUsage(): array
    {
        return [
            'root_free_bytes' => @disk_free_space($this->rootPath) ?: null,
            'logs_bytes' => $this->directorySize($this->rootPath . '/storage/logs'),
            'uploads_bytes' => $this->directorySize($this->rootPath . '/storage/uploads'),
            'backups_bytes' => $this->directorySize($this->rootPath . '/storage/backups'),
        ];
    }

    /** @return array<string, mixed> */
    private function databaseOverview(): array
    {
        try {
            $tables = (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
            $migrations = (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            return [
                'tables_count' => $tables,
                'migrations_count' => $migrations,
            ];
        } catch (Throwable) {
            return [
                'tables_count' => 0,
                'migrations_count' => 0,
            ];
        }
    }

    /** @param array<string, mixed> $readiness @param array<string, mixed> $backup @return list<string> */
    private function nextOperatorActions(array $readiness, array $backup): array
    {
        $actions = [];
        if (($readiness['status'] ?? 'unknown') === 'not_ready') {
            $actions[] = 'Open /api/ready and resolve failing readiness checks before Step 21 validation.';
        }
        if (($backup['latest_backup'] ?? null) === null) {
            $actions[] = 'Create the first SQLite backup from the admin observability console.';
        }
        $actions[] = 'Run the Step 21 smoke test after production readiness smoke passes.';
        $actions[] = 'Use the deployment runbook before any pilot/demo deploy.';
        return $actions;
    }

    /** @param array<string, mixed> $http @param array<string, mixed> $backup */
    private function overallStatus(string $readinessStatus, array $http, array $backup): string
    {
        if ($readinessStatus === 'not_ready' || ((int) ($http['errors_5xx'] ?? 0)) > 0) {
            return 'attention_required';
        }
        if (($backup['latest_backup'] ?? null) === null || $readinessStatus === 'degraded') {
            return 'degraded_but_operable';
        }
        return 'operable';
    }

    private function directorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $total = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total += $file->getSize();
            }
        }
        return $total;
    }
}
