<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Infrastructure;

use PDO;
use Reborn\Marketplace\Domain\RepairPathDecision;
use Reborn\Marketplace\Domain\RepairPathDecisionRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteRepairPathDecisionRepository implements RepairPathDecisionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createCompleted(string $repairCaseId, ?string $recognitionJobId, string $requestedBy, array $result): RepairPathDecision
    {
        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO repair_path_decisions (id, repair_case_id, recognition_job_id, requested_by, status, result_json, created_at, completed_at) VALUES (:id, :repair_case_id, :recognition_job_id, :requested_by, :status, :result_json, :created_at, :completed_at)');
        $stmt->execute([
            'id' => $id,
            'repair_case_id' => $repairCaseId,
            'recognition_job_id' => $recognitionJobId,
            'requested_by' => $requestedBy,
            'status' => 'completed',
            'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'completed_at' => $now,
        ]);

        $decision = $this->find($id);
        if ($decision === null) {
            throw new \RuntimeException('Repair path decision creation failed.');
        }

        return $decision;
    }

    public function find(string $id): ?RepairPathDecision
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_path_decisions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? RepairPathDecision::fromRow($row) : null;
    }

    public function listByRepairCase(string $repairCaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_path_decisions WHERE repair_case_id = :repair_case_id ORDER BY created_at DESC');
        $stmt->execute(['repair_case_id' => $repairCaseId]);

        return array_map(static fn(array $row): RepairPathDecision => RepairPathDecision::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
