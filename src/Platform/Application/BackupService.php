<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use RuntimeException;
use Throwable;
use Reborn\Shared\Support\Uuid;

final class BackupService
{
    /** @param array<string, mixed> $databaseConfig */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $databaseConfig,
        private readonly string $rootPath,
    ) {
    }

    /** @return array<string, mixed> */
    public function create(?string $triggeredBy, string $triggeredVia = 'api'): array
    {
        $id = Uuid::v4();
        $createdAt = gmdate('c');
        $databasePath = $this->sqliteDatabasePath();
        $backupDir = $this->rootPath . '/storage/backups';

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $target = $backupDir . '/reborn-' . gmdate('Ymd-His') . '-' . substr($id, 0, 8) . '.sqlite';
        $row = [
            'id' => $id,
            'backup_file' => $target,
            'status' => 'failed',
            'size_bytes' => 0,
            'database_size_bytes' => is_file($databasePath) ? (int) filesize($databasePath) : 0,
            'triggered_by' => $triggeredBy,
            'triggered_via' => $triggeredVia,
            'error_message' => null,
            'created_at' => $createdAt,
        ];

        try {
            if (!is_file($databasePath)) {
                throw new RuntimeException('SQLite database file not found. Run php scripts/setup-dev.php first.');
            }

            if (!copy($databasePath, $target)) {
                throw new RuntimeException('Unable to copy SQLite database to backup directory.');
            }

            $row['status'] = 'completed';
            $row['size_bytes'] = (int) filesize($target);
        } catch (Throwable $exception) {
            $row['error_message'] = $exception->getMessage();
        }

        $this->persist($row);
        return $this->publicRow($row);
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_backup_runs ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(fn (array $row): array => $this->publicRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $latest = $this->latest(1)[0] ?? null;
        return [
            'latest_backup' => $latest,
            'backup_dir' => $this->relativePath($this->rootPath . '/storage/backups'),
            'database_path' => $this->relativePath($this->sqliteDatabasePath()),
            'database_size_bytes' => is_file($this->sqliteDatabasePath()) ? (int) filesize($this->sqliteDatabasePath()) : 0,
            'restore_checklist' => [
                'Stop the PHP development server or production worker.',
                'Copy the selected backup over storage/database/reborn.sqlite.',
                'Run php scripts/setup-dev.php to confirm migrations are aligned.',
                'Start the server and run smoke-production-readiness.ps1, then smoke-observability-ops.ps1.',
            ],
        ];
    }

    /** @param array<string, mixed> $row */
    private function persist(array $row): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_backup_runs (id, backup_file, status, size_bytes, database_size_bytes, triggered_by, triggered_via, error_message, created_at) VALUES (:id, :backup_file, :status, :size_bytes, :database_size_bytes, :triggered_by, :triggered_via, :error_message, :created_at)');
        $stmt->execute($row);
    }

    private function sqliteDatabasePath(): string
    {
        $path = (string) ($this->databaseConfig['database'] ?? $this->rootPath . '/storage/database/reborn.sqlite');
        return $path;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function publicRow(array $row): array
    {
        $row['size_bytes'] = (int) ($row['size_bytes'] ?? 0);
        $row['database_size_bytes'] = (int) ($row['database_size_bytes'] ?? 0);
        $row['backup_file'] = $this->relativePath((string) ($row['backup_file'] ?? ''));
        return $row;
    }

    private function relativePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $root = rtrim(str_replace('\\', '/', $this->rootPath), '/');
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }

        return $path;
    }
}
