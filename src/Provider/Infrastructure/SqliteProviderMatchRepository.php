<?php

declare(strict_types=1);

namespace Reborn\Provider\Infrastructure;

use PDO;
use Reborn\Provider\Domain\ProviderMatch;
use Reborn\Provider\Domain\ProviderMatchRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteProviderMatchRepository implements ProviderMatchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createCompleted(string $repairCaseId, ?string $repairPathDecisionId, string $requestedBy, array $result): ProviderMatch
    {
        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO provider_matches (id, repair_case_id, repair_path_decision_id, requested_by, status, result_json, created_at, completed_at) VALUES (:id, :repair_case_id, :repair_path_decision_id, :requested_by, :status, :result_json, :created_at, :completed_at)');
        $stmt->execute([
            'id' => $id,
            'repair_case_id' => $repairCaseId,
            'repair_path_decision_id' => $repairPathDecisionId,
            'requested_by' => $requestedBy,
            'status' => 'completed',
            'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'completed_at' => $now,
        ]);

        $match = $this->find($id);
        if ($match === null) {
            throw new \RuntimeException('Provider match creation failed.');
        }

        return $match;
    }

    public function find(string $id): ?ProviderMatch
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_matches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderMatch::fromRow($row) : null;
    }

    public function listByRepairCase(string $repairCaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_matches WHERE repair_case_id = :repair_case_id ORDER BY created_at DESC');
        $stmt->execute(['repair_case_id' => $repairCaseId]);

        return array_map(static fn(array $row): ProviderMatch => ProviderMatch::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
