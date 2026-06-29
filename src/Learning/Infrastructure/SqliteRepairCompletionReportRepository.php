<?php

declare(strict_types=1);

namespace Reborn\Learning\Infrastructure;

use PDO;
use Reborn\Fulfilment\Domain\RepairFulfilment;
use Reborn\Learning\Domain\RepairCompletionReport;
use Reborn\Learning\Domain\RepairCompletionReportRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteRepairCompletionReportRepository implements RepairCompletionReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $payload */
    public function createFromFulfilment(RepairFulfilment $fulfilment, string $reportedBy, array $payload): RepairCompletionReport
    {
        $existing = $this->listByFulfilment($fulfilment->id);
        if ($existing !== []) {
            return $existing[0];
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $outcomeStatus = (string) ($payload['outcome_status'] ?? 'successful');
        $functionalResult = (string) ($payload['functional_result'] ?? 'object_returned_to_function');
        $customerConfirmed = (bool) ($payload['customer_confirmed'] ?? false);
        $objectSaved = (bool) ($payload['object_saved'] ?? true);
        $co2AvoidedGrams = max(0, (int) ($payload['co2_avoided_grams'] ?? 0));
        $evidenceAttachmentIds = $payload['evidence_attachment_ids'] ?? [];
        if (!is_array($evidenceAttachmentIds)) {
            $evidenceAttachmentIds = [];
        }

        $outcome = [
            'summary' => (string) ($payload['summary'] ?? 'Repair completed and object returned to function.'),
            'repair_method' => (string) ($payload['repair_method'] ?? 'provider_validated_repair'),
            'material_used' => (string) ($payload['material_used'] ?? 'provider_selected_material'),
            'quality_checks' => array_values(is_array($payload['quality_checks'] ?? null) ? $payload['quality_checks'] : ['fit_checked', 'function_checked']),
            'failure_reason' => $payload['failure_reason'] ?? null,
            'notes' => (string) ($payload['notes'] ?? ''),
        ];

        $stmt = $this->pdo->prepare('INSERT INTO repair_completion_reports (id, fulfilment_id, repair_order_id, repair_case_id, provider_id, reported_by, status, outcome_status, functional_result, customer_confirmed, object_saved, co2_avoided_grams, evidence_attachment_ids, outcome_json, created_at, updated_at) VALUES (:id, :fulfilment_id, :repair_order_id, :repair_case_id, :provider_id, :reported_by, :status, :outcome_status, :functional_result, :customer_confirmed, :object_saved, :co2_avoided_grams, :evidence_attachment_ids, :outcome_json, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'fulfilment_id' => $fulfilment->id,
            'repair_order_id' => $fulfilment->repairOrderId,
            'repair_case_id' => $fulfilment->repairCaseId,
            'provider_id' => $fulfilment->providerId,
            'reported_by' => $reportedBy,
            'status' => 'recorded',
            'outcome_status' => $outcomeStatus,
            'functional_result' => $functionalResult,
            'customer_confirmed' => $customerConfirmed ? 1 : 0,
            'object_saved' => $objectSaved ? 1 : 0,
            'co2_avoided_grams' => $co2AvoidedGrams,
            'evidence_attachment_ids' => json_encode(array_values(array_map('strval', $evidenceAttachmentIds)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'outcome_json' => json_encode($outcome, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->pdo->prepare('UPDATE repair_cases SET status = :status, updated_at = :updated_at WHERE id = :id')->execute([
            'id' => $fulfilment->repairCaseId,
            'status' => $outcomeStatus === 'successful' ? 'completed' : 'completion_review',
            'updated_at' => $now,
        ]);

        $this->pdo->prepare('UPDATE repair_orders SET status = :status, confirmed_at = COALESCE(confirmed_at, :confirmed_at) WHERE id = :id')->execute([
            'id' => $fulfilment->repairOrderId,
            'status' => $outcomeStatus === 'successful' ? 'completed' : 'completion_review',
            'confirmed_at' => $now,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('Repair completion report creation failed.');
    }

    public function find(string $id): ?RepairCompletionReport
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_completion_reports WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? RepairCompletionReport::fromRow($row) : null;
    }

    public function listByFulfilment(string $fulfilmentId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_completion_reports WHERE fulfilment_id = :fulfilment_id ORDER BY created_at DESC');
        $stmt->execute(['fulfilment_id' => $fulfilmentId]);

        return array_map(static fn(array $row): RepairCompletionReport => RepairCompletionReport::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
