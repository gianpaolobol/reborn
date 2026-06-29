<?php

declare(strict_types=1);

namespace Reborn\Learning\Infrastructure;

use PDO;
use Reborn\Learning\Domain\RepairCompletionReport;
use Reborn\Learning\Domain\RepairLearningEvent;
use Reborn\Learning\Domain\RepairLearningEventRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteRepairLearningEventRepository implements RepairLearningEventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $signal */
    public function record(RepairCompletionReport $report, string $eventType, array $signal, float $confidenceDelta): RepairLearningEvent
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO repair_learning_events (id, completion_report_id, fulfilment_id, repair_case_id, provider_id, event_type, signal_json, confidence_delta, created_at) VALUES (:id, :completion_report_id, :fulfilment_id, :repair_case_id, :provider_id, :event_type, :signal_json, :confidence_delta, :created_at)');
        $stmt->execute([
            'id' => $id,
            'completion_report_id' => $report->id,
            'fulfilment_id' => $report->fulfilmentId,
            'repair_case_id' => $report->repairCaseId,
            'provider_id' => $report->providerId,
            'event_type' => $eventType,
            'signal_json' => json_encode($signal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'confidence_delta' => $confidenceDelta,
            'created_at' => $now,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('Repair learning event creation failed.');
    }

    public function find(string $id): ?RepairLearningEvent
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_learning_events WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? RepairLearningEvent::fromRow($row) : null;
    }

    public function listByRepairCase(string $repairCaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_learning_events WHERE repair_case_id = :repair_case_id ORDER BY created_at DESC');
        $stmt->execute(['repair_case_id' => $repairCaseId]);

        return array_map(static fn(array $row): RepairLearningEvent => RepairLearningEvent::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
