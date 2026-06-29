<?php

declare(strict_types=1);

namespace Reborn\Repair\Infrastructure;

use PDO;
use Reborn\Repair\Domain\RepairCase;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteRepairCaseRepository implements RepairCaseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(int $limit = 50, ?string $ownerId = null): array
    {
        if ($ownerId !== null) {
            $stmt = $this->pdo->prepare('SELECT * FROM repair_cases WHERE owner_id = :owner_id ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('owner_id', $ownerId);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(static fn(array $row): RepairCase => RepairCase::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT * FROM repair_cases ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn(array $row): RepairCase => RepairCase::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(string $id): ?RepairCase
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_cases WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? RepairCase::fromRow($row) : null;
    }

    public function create(array $data): RepairCase
    {
        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO repair_cases (id, owner_id, title, description, category, status, recognized_product, recognized_component, confidence_score, created_at, updated_at) VALUES (:id, :owner_id, :title, :description, :category, :status, NULL, NULL, 0, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'owner_id' => $data['owner_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'status' => 'intake_received',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $case = $this->find($id);
        if ($case === null) {
            throw new \RuntimeException('Repair case creation failed.');
        }

        return $case;
    }

    public function updateDiagnosis(string $id, array $diagnosis): RepairCase
    {
        $stmt = $this->pdo->prepare('UPDATE repair_cases SET status = :status, recognized_product = :recognized_product, recognized_component = :recognized_component, confidence_score = :confidence_score, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'status' => 'diagnosed',
            'recognized_product' => $diagnosis['recognized_product'] ?? null,
            'recognized_component' => $diagnosis['recognized_component'] ?? null,
            'confidence_score' => $diagnosis['confidence_score'] ?? 0,
            'updated_at' => gmdate('c'),
        ]);

        $case = $this->find($id);
        if ($case === null) {
            throw new \RuntimeException('Repair case diagnosis update failed.');
        }

        return $case;
    }
}
