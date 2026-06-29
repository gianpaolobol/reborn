<?php

declare(strict_types=1);

namespace Reborn\Shared\Database;

use PDO;

final class MigrationRunner
{
    public function __construct(private readonly PDO $pdo, private readonly string $migrationPath)
    {
    }

    public function run(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT NOT NULL UNIQUE, executed_at TEXT NOT NULL)');

        foreach (glob($this->migrationPath . '/*.sql') ?: [] as $file) {
            $filename = basename($file);
            if ($this->alreadyExecuted($filename)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }

            $this->pdo->beginTransaction();
            $this->pdo->exec($sql);
            $stmt = $this->pdo->prepare('INSERT INTO migrations (filename, executed_at) VALUES (:filename, :executed_at)');
            $stmt->execute([
                'filename' => $filename,
                'executed_at' => gmdate('c'),
            ]);
            $this->pdo->commit();
        }
    }

    private function alreadyExecuted(string $filename): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM migrations WHERE filename = :filename');
        $stmt->execute(['filename' => $filename]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
