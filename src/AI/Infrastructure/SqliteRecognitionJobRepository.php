<?php

declare(strict_types=1);

namespace Reborn\AI\Infrastructure;

use PDO;
use Reborn\AI\Domain\RecognitionJob;
use Reborn\AI\Domain\RecognitionJobRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteRecognitionJobRepository implements RecognitionJobRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $repairCaseId, string $requestedBy, array $attachmentIds): RecognitionJob
    {
        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO recognition_jobs (id, repair_case_id, requested_by, status, input_attachment_ids, result_json, error_message, created_at, started_at, completed_at) VALUES (:id, :repair_case_id, :requested_by, :status, :input_attachment_ids, NULL, NULL, :created_at, NULL, NULL)');
        $stmt->execute([
            'id' => $id,
            'repair_case_id' => $repairCaseId,
            'requested_by' => $requestedBy,
            'status' => 'requested',
            'input_attachment_ids' => json_encode(array_values($attachmentIds), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        $job = $this->find($id);
        if ($job === null) {
            throw new \RuntimeException('Recognition job creation failed.');
        }

        return $job;
    }

    public function markProcessing(string $id): RecognitionJob
    {
        $stmt = $this->pdo->prepare('UPDATE recognition_jobs SET status = :status, started_at = :started_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'status' => 'processing',
            'started_at' => gmdate('c'),
        ]);

        return $this->require($id);
    }

    public function complete(string $id, array $result): RecognitionJob
    {
        $stmt = $this->pdo->prepare('UPDATE recognition_jobs SET status = :status, result_json = :result_json, error_message = NULL, completed_at = :completed_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'status' => 'completed',
            'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'completed_at' => gmdate('c'),
        ]);

        return $this->require($id);
    }

    public function fail(string $id, string $message): RecognitionJob
    {
        $stmt = $this->pdo->prepare('UPDATE recognition_jobs SET status = :status, error_message = :error_message, completed_at = :completed_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => gmdate('c'),
        ]);

        return $this->require($id);
    }

    public function find(string $id): ?RecognitionJob
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recognition_jobs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? RecognitionJob::fromRow($row) : null;
    }

    public function listByRepairCase(string $repairCaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recognition_jobs WHERE repair_case_id = :repair_case_id ORDER BY created_at DESC');
        $stmt->execute(['repair_case_id' => $repairCaseId]);

        return array_map(static fn(array $row): RecognitionJob => RecognitionJob::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function require(string $id): RecognitionJob
    {
        $job = $this->find($id);
        if ($job === null) {
            throw new \RuntimeException('Recognition job not found after update.');
        }

        return $job;
    }
}
