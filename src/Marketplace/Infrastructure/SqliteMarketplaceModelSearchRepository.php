<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Infrastructure;

use PDO;
use Reborn\Shared\Support\Uuid;

final class SqliteMarketplaceModelSearchRepository implements MarketplaceModelSearchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $queryJson */
    public function create(string $repairCaseId, string $recognitionJobId, array $queryJson): string
    {
        $this->ensureTables();

        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO marketplace_model_searches (id, recognition_job_id, repair_case_id, status, query_json, result_summary_json, created_at, completed_at) VALUES (:id, :recognition_job_id, :repair_case_id, :status, :query_json, NULL, :created_at, NULL)');
        $stmt->execute([
            'id' => $id,
            'recognition_job_id' => $recognitionJobId,
            'repair_case_id' => $repairCaseId,
            'status' => 'searching',
            'query_json' => json_encode($queryJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        return $id;
    }

    /** @param array<string, mixed> $candidate */
    public function addCandidate(string $searchId, array $candidate): void
    {
        $this->ensureTables();

        $stmt = $this->pdo->prepare('INSERT INTO marketplace_model_candidates (id, marketplace_search_id, source, title, url, thumbnail_url, score, license, download_available, file_type, match_reason, raw_json, created_at) VALUES (:id, :marketplace_search_id, :source, :title, :url, :thumbnail_url, :score, :license, :download_available, :file_type, :match_reason, :raw_json, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'marketplace_search_id' => $searchId,
            'source' => (string) ($candidate['source'] ?? 'web'),
            'title' => (string) ($candidate['title'] ?? 'Risultato modello 3D'),
            'url' => (string) ($candidate['url'] ?? ''),
            'thumbnail_url' => (string) ($candidate['thumbnail_url'] ?? ''),
            'score' => (int) ($candidate['score'] ?? 0),
            'license' => (string) ($candidate['license'] ?? ''),
            'download_available' => !empty($candidate['download_available']) ? 1 : 0,
            'file_type' => (string) ($candidate['file_type'] ?? ''),
            'match_reason' => (string) ($candidate['match_reason'] ?? ''),
            'raw_json' => json_encode($candidate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => gmdate('c'),
        ]);
    }

    /** @param array<string, mixed> $summary */
    public function complete(string $searchId, string $status, array $summary): void
    {
        $this->ensureTables();

        $stmt = $this->pdo->prepare('UPDATE marketplace_model_searches SET status = :status, result_summary_json = :result_summary_json, completed_at = :completed_at WHERE id = :id');
        $stmt->execute([
            'id' => $searchId,
            'status' => $status,
            'result_summary_json' => json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'completed_at' => gmdate('c'),
        ]);
    }

    private function ensureTables(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_model_searches (id TEXT PRIMARY KEY, recognition_job_id TEXT NOT NULL, repair_case_id TEXT NOT NULL, status TEXT NOT NULL, query_json TEXT NOT NULL DEFAULT '{}', result_summary_json TEXT, created_at TEXT NOT NULL, completed_at TEXT)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_model_candidates (id TEXT PRIMARY KEY, marketplace_search_id TEXT NOT NULL, source TEXT NOT NULL, title TEXT NOT NULL, url TEXT NOT NULL, thumbnail_url TEXT NOT NULL DEFAULT '', score INTEGER NOT NULL DEFAULT 0, license TEXT NOT NULL DEFAULT '', download_available INTEGER NOT NULL DEFAULT 0, file_type TEXT NOT NULL DEFAULT '', match_reason TEXT NOT NULL DEFAULT '', raw_json TEXT NOT NULL DEFAULT '{}', created_at TEXT NOT NULL)");
    }
}
