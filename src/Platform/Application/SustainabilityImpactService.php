<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class SustainabilityImpactService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'summary' => [
                'active_factors' => $this->count('platform_sustainability_factors', "status = 'active'"),
                'impact_records' => $this->count('platform_repair_impact_records'),
                'calculated_impacts' => $this->count('platform_repair_impact_records', "status IN ('calculated','accepted','published_internal')"),
                'objects_saved' => $this->count('platform_repair_impact_records', "status IN ('calculated','accepted','published_internal') AND repair_score >= 50"),
                'co2e_avoided_kg' => $this->sum('platform_repair_impact_records', 'co2e_avoided_kg', "status IN ('calculated','accepted','published_internal')"),
                'waste_diverted_kg' => $this->sum('platform_repair_impact_records', 'waste_diverted_kg', "status IN ('calculated','accepted','published_internal')"),
                'material_saved_kg' => $this->sum('platform_repair_impact_records', 'material_saved_kg', "status IN ('calculated','accepted','published_internal')"),
                'open_insights' => $this->count('platform_repair_outcome_insights', "status IN ('open','investigating')"),
                'open_reviews' => $this->count('platform_impact_review_items', "status IN ('open','assigned')"),
            ],
            'factors' => $this->sustainabilityFactors('active'),
            'latest_impacts' => $this->impactRecords('all', 8),
            'latest_snapshots' => $this->circularitySnapshots(6),
            'open_insights' => $this->outcomeInsights('active', 8),
            'open_reviews' => $this->impactReviewItems('active', 8),
            'scope_note' => 'Step 36 creates local/pilot sustainability impact and circularity metrics. These are not certified public environmental claims and require validated factors before production use.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function sustainabilityFactors(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_sustainability_factors';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY category ASC, factor_key ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $row): array {
            $row['default_value'] = (float) $row['default_value'];
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function impactRecords(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT i.*, a.acceptance_code, a.acceptance_decision, a.satisfaction_score, d.dispatch_code, p.summary AS proof_summary FROM platform_repair_impact_records i LEFT JOIN platform_customer_acceptance_records a ON a.id = i.acceptance_record_id LEFT JOIN platform_fulfilment_dispatches d ON d.id = i.dispatch_id LEFT JOIN platform_proof_of_repair_records p ON p.id = i.proof_of_repair_id';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE i.status IN ('draft','calculated','needs_review')";
            } else {
                $sql .= ' WHERE i.status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY i.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeImpactRecord'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createImpactRecord(array $body, ?string $userId): array
    {
        $acceptanceId = trim((string) ($body['acceptance_record_id'] ?? '')) ?: null;
        $acceptance = $acceptanceId ? $this->findAcceptance($acceptanceId) : $this->latestAcceptedAcceptance();
        if ($acceptanceId !== null && $acceptance === null) {
            throw new ValidationException(['acceptance_record_id' => ['Customer acceptance record was not found.']]);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'IMPACT-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $category = trim((string) ($body['category'] ?? $this->inferCategory($acceptance))) ?: 'general_repair';
        $weight = max(0.05, min(250.0, (float) ($body['object_weight_kg'] ?? 0.45)));
        $lifespan = max(1, min(120, (int) ($body['estimated_lifespan_months'] ?? 24)));

        $stmt = $this->pdo->prepare('INSERT INTO platform_repair_impact_records (id, impact_code, acceptance_record_id, dispatch_id, proof_of_repair_id, repair_case_id, repair_order_id, category, status, object_weight_kg, estimated_lifespan_months, co2e_avoided_kg, waste_diverted_kg, material_saved_kg, repair_score, confidence_level, evidence_json, calculated_at, created_by, created_at, updated_at) VALUES (:id, :impact_code, :acceptance_record_id, :dispatch_id, :proof_of_repair_id, :repair_case_id, :repair_order_id, :category, :status, :object_weight_kg, :estimated_lifespan_months, :co2e_avoided_kg, :waste_diverted_kg, :material_saved_kg, :repair_score, :confidence_level, :evidence_json, :calculated_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'impact_code' => $code,
            'acceptance_record_id' => $acceptance['id'] ?? $acceptanceId,
            'dispatch_id' => trim((string) ($body['dispatch_id'] ?? ($acceptance['dispatch_id'] ?? ''))) ?: null,
            'proof_of_repair_id' => trim((string) ($body['proof_of_repair_id'] ?? ($acceptance['proof_of_repair_id'] ?? ''))) ?: null,
            'repair_case_id' => trim((string) ($body['repair_case_id'] ?? ($acceptance['repair_case_id'] ?? ''))) ?: null,
            'repair_order_id' => trim((string) ($body['repair_order_id'] ?? ($acceptance['repair_order_id'] ?? ''))) ?: null,
            'category' => $category,
            'status' => 'draft',
            'object_weight_kg' => $weight,
            'estimated_lifespan_months' => $lifespan,
            'co2e_avoided_kg' => 0,
            'waste_diverted_kg' => 0,
            'material_saved_kg' => 0,
            'repair_score' => 0,
            'confidence_level' => trim((string) ($body['confidence_level'] ?? 'pilot_estimate')) ?: 'pilot_estimate',
            'evidence_json' => json_encode($body['evidence'] ?? ['source' => 'prototype_step_36', 'acceptance_available' => $acceptance !== null], JSON_THROW_ON_ERROR),
            'calculated_at' => null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('impact_record_created', 'repair_impact_record', $id, sprintf('Repair impact record %s created.', $code), ['category' => $category, 'object_weight_kg' => $weight], $userId);
        return $this->requireImpactRecord($id);
    }

    /** @return array<string, mixed> */
    public function calculateImpactRecord(string $id, array $body, ?string $userId): array
    {
        $record = $this->requireImpactRecord($id);
        $category = trim((string) ($body['category'] ?? $record['category'])) ?: 'general_repair';
        $weight = max(0.05, min(250.0, (float) ($body['object_weight_kg'] ?? $record['object_weight_kg'])));
        $lifespan = max(1, min(120, (int) ($body['estimated_lifespan_months'] ?? $record['estimated_lifespan_months'])));
        $co2Factor = $this->factorForCategory($category);
        $wasteRatio = $this->factorValue('waste_diversion_ratio', 0.92);
        $materialRatio = $this->factorValue('material_saved_ratio', 0.78);
        $scoreMonths = max(1.0, $this->factorValue('lifespan_score_months', 24.0));

        $co2e = round($weight * $co2Factor * min(1.5, max(0.35, $lifespan / 24)), 3);
        $waste = round($weight * $wasteRatio, 3);
        $material = round($weight * $materialRatio, 3);
        $score = max(1, min(100, (int) round(($lifespan / $scoreMonths) * 55 + min(35, $co2e * 1.4) + min(10, $waste * 2))));
        $status = $score < 40 ? 'needs_review' : 'calculated';
        $now = gmdate('c');
        $evidence = $record['evidence'] ?? [];
        $evidence['calculation'] = [
            'co2_factor' => $co2Factor,
            'waste_diversion_ratio' => $wasteRatio,
            'material_saved_ratio' => $materialRatio,
            'formula' => 'pilot_estimate_weight_factor_lifespan',
            'public_claims_allowed' => false,
        ];

        $stmt = $this->pdo->prepare('UPDATE platform_repair_impact_records SET category = :category, status = :status, object_weight_kg = :object_weight_kg, estimated_lifespan_months = :estimated_lifespan_months, co2e_avoided_kg = :co2e_avoided_kg, waste_diverted_kg = :waste_diverted_kg, material_saved_kg = :material_saved_kg, repair_score = :repair_score, evidence_json = :evidence_json, calculated_at = :calculated_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'category' => $category,
            'status' => $status,
            'object_weight_kg' => $weight,
            'estimated_lifespan_months' => $lifespan,
            'co2e_avoided_kg' => $co2e,
            'waste_diverted_kg' => $waste,
            'material_saved_kg' => $material,
            'repair_score' => $score,
            'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'calculated_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);

        if ($status === 'needs_review' || $record['acceptance_decision'] === 'rejected_with_issue') {
            $this->createReviewItem('repair_impact_record', $id, $status === 'needs_review' ? 'medium' : 'high', 'Impact record needs human review before inclusion in circularity reporting.', $userId);
        }
        $this->audit('impact_record_calculated', 'repair_impact_record', $id, sprintf('Impact record %s calculated.', $record['impact_code']), ['co2e_avoided_kg' => $co2e, 'waste_diverted_kg' => $waste, 'repair_score' => $score], $userId);

        return $this->requireImpactRecord($id);
    }

    /** @return list<array<string, mixed>> */
    public function circularitySnapshots(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_circularity_metric_snapshots ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeSnapshot'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createCircularitySnapshot(array $body, ?string $userId): array
    {
        $scope = trim((string) ($body['scope'] ?? 'pilot')) ?: 'pilot';
        $periodStart = trim((string) ($body['period_start'] ?? gmdate('Y-m-01\T00:00:00\Z'))) ?: null;
        $periodEnd = trim((string) ($body['period_end'] ?? gmdate('c'))) ?: null;
        $impactRows = $this->aggregateImpacts();
        $credits = $this->tableExists('platform_credit_transactions') ? (int) $this->pdo->query("SELECT COALESCE(SUM(amount_credits), 0) FROM platform_credit_transactions WHERE status IN ('posted','approved')")->fetchColumn() : 0;
        $impactScore = max(0, min(100, (int) round(($impactRows['objects_saved'] * 10) + min(60, $impactRows['co2e_avoided_kg'] * 1.2) + min(30, $impactRows['waste_diverted_kg'] * 5))));
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'CIRC-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $calculation = [
            'source' => 'step_36_local_pilot',
            'method' => 'aggregate_calculated_impact_records',
            'public_claims_allowed' => false,
            'notes' => 'Snapshot is a local pilot estimate and not a certified environmental report.',
        ];
        $stmt = $this->pdo->prepare('INSERT INTO platform_circularity_metric_snapshots (id, snapshot_code, scope, status, period_start, period_end, objects_saved, accepted_repairs, co2e_avoided_kg, waste_diverted_kg, material_saved_kg, repair_credits_issued, impact_score, calculation_json, created_by, created_at, updated_at) VALUES (:id, :snapshot_code, :scope, :status, :period_start, :period_end, :objects_saved, :accepted_repairs, :co2e_avoided_kg, :waste_diverted_kg, :material_saved_kg, :repair_credits_issued, :impact_score, :calculation_json, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'snapshot_code' => $code,
            'scope' => $scope,
            'status' => trim((string) ($body['status'] ?? 'draft')) ?: 'draft',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'objects_saved' => $impactRows['objects_saved'],
            'accepted_repairs' => $impactRows['accepted_repairs'],
            'co2e_avoided_kg' => $impactRows['co2e_avoided_kg'],
            'waste_diverted_kg' => $impactRows['waste_diverted_kg'],
            'material_saved_kg' => $impactRows['material_saved_kg'],
            'repair_credits_issued' => $credits,
            'impact_score' => $impactScore,
            'calculation_json' => json_encode($calculation, JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('circularity_snapshot_created', 'circularity_snapshot', $id, sprintf('Circularity snapshot %s created.', $code), ['objects_saved' => $impactRows['objects_saved'], 'co2e_avoided_kg' => $impactRows['co2e_avoided_kg']], $userId);
        return $this->circularitySnapshots(1)[0];
    }

    /** @return list<array<string, mixed>> */
    public function outcomeInsights(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_repair_outcome_insights';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE status IN ('open','investigating')";
            } else {
                $sql .= ' WHERE status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function evaluateOutcomeInsights(array $body, ?string $userId): array
    {
        $created = [];
        $aggregate = $this->aggregateImpacts();
        if ($aggregate['objects_saved'] === 0) {
            $created[] = $this->createInsight('missing_impact_records', 'system', 'sustainability-impact', 'warning', 'No calculated repair impact records yet', 'Circularity reporting has no calculated repair impacts to aggregate.', 'Create or calculate at least one impact record after customer acceptance.', ['objects_saved' => 0], $userId);
        }
        if ($aggregate['co2e_avoided_kg'] > 0 && $aggregate['co2e_avoided_kg'] < 2) {
            $created[] = $this->createInsight('low_impact_signal', 'system', 'sustainability-impact', 'info', 'Impact signal is still low', 'The current pilot has measurable but small estimated CO2e avoided.', 'Use more repair completions and validated product-specific factors before public impact reporting.', $aggregate, $userId);
        }
        $lowScore = $this->count('platform_repair_impact_records', "status IN ('calculated','needs_review') AND repair_score < 50");
        if ($lowScore > 0) {
            $created[] = $this->createInsight('low_repair_score', 'repair_impact_record', null, 'warning', 'Some repair impact records have low repair score', 'One or more records should be reviewed before inclusion in circularity snapshots.', 'Review impact records with low score and improve evidence before publishing internally.', ['low_score_records' => $lowScore], $userId);
        }
        if ($created === []) {
            $created[] = $this->createInsight('healthy_pilot_signal', 'system', 'sustainability-impact', 'info', 'Pilot circularity metrics are internally consistent', 'Calculated impact records are available and no low-score warning was detected.', 'Keep collecting accepted repair outcomes and validate factors before external claims.', $aggregate, $userId);
        }
        $this->audit('outcome_insights_evaluated', 'repair_outcome_insights', null, 'Repair outcome insights evaluated.', ['created' => count($created), 'requested_by_payload' => $body], $userId);
        return ['created_insights' => $created, 'aggregate' => $aggregate];
    }

    /** @return list<array<string, mixed>> */
    public function impactReviewItems(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_impact_review_items';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE status IN ('open','assigned')";
            } else {
                $sql .= ' WHERE status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function reviewImpactItem(string $id, array $body, ?string $userId): array
    {
        $decision = trim((string) ($body['decision'] ?? 'accepted_for_internal_reporting')) ?: 'accepted_for_internal_reporting';
        $notes = trim((string) ($body['notes'] ?? 'Reviewed from Step 36 pilot console.')) ?: null;
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_impact_review_items SET status = :status, decision = :decision, notes = :notes, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'resolved',
            'decision' => $decision,
            'notes' => $notes,
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
        if ($stmt->rowCount() === 0) {
            throw new ValidationException(['id' => ['Impact review item was not found.']]);
        }
        $this->audit('impact_review_completed', 'impact_review_item', $id, 'Impact review completed.', ['decision' => $decision], $userId);
        $stmt = $this->pdo->prepare('SELECT * FROM platform_impact_review_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_sustainability_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function requireImpactRecord(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT i.*, a.acceptance_code, a.acceptance_decision, a.satisfaction_score, d.dispatch_code, p.summary AS proof_summary FROM platform_repair_impact_records i LEFT JOIN platform_customer_acceptance_records a ON a.id = i.acceptance_record_id LEFT JOIN platform_fulfilment_dispatches d ON d.id = i.dispatch_id LEFT JOIN platform_proof_of_repair_records p ON p.id = i.proof_of_repair_id WHERE i.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['Repair impact record was not found.']]);
        }
        return $this->normalizeImpactRecord($row);
    }

    /** @return array<string, mixed> */
    private function normalizeImpactRecord(array $row): array
    {
        foreach (['object_weight_kg', 'co2e_avoided_kg', 'waste_diverted_kg', 'material_saved_kg'] as $key) {
            $row[$key] = (float) ($row[$key] ?? 0);
        }
        foreach (['estimated_lifespan_months', 'repair_score'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '{}');
        unset($row['evidence_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeSnapshot(array $row): array
    {
        foreach (['co2e_avoided_kg', 'waste_diverted_kg', 'material_saved_kg'] as $key) {
            $row[$key] = (float) ($row[$key] ?? 0);
        }
        foreach (['objects_saved', 'accepted_repairs', 'repair_credits_issued', 'impact_score'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        $row['calculation'] = $this->decodeJson($row['calculation_json'] ?? '{}');
        unset($row['calculation_json']);
        return $row;
    }

    /** @return array<string, mixed>|null */
    private function latestAcceptedAcceptance(): ?array
    {
        if (!$this->tableExists('platform_customer_acceptance_records')) {
            return null;
        }
        $row = $this->pdo->query("SELECT * FROM platform_customer_acceptance_records ORDER BY CASE WHEN status = 'accepted' THEN 0 ELSE 1 END, created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findAcceptance(string $id): ?array
    {
        if (!$this->tableExists('platform_customer_acceptance_records')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM platform_customer_acceptance_records WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed>|null $acceptance */
    private function inferCategory(?array $acceptance): string
    {
        $summary = strtolower((string) ($acceptance['issue_summary'] ?? $acceptance['acceptance_decision'] ?? ''));
        if (str_contains($summary, 'electronic') || str_contains($summary, 'battery')) {
            return 'electronics';
        }
        if (str_contains($summary, 'plastic') || str_contains($summary, 'fit')) {
            return 'plastic_part';
        }
        return 'general_repair';
    }

    private function factorForCategory(string $category): float
    {
        return match ($category) {
            'electronics' => $this->factorValue('electronics_repair_co2e_per_kg', 18.0),
            'plastic_part', 'printed_part' => $this->factorValue('plastic_part_repair_co2e_per_kg', 3.2),
            default => $this->factorValue('generic_repair_co2e_per_kg', 6.5),
        };
    }

    private function factorValue(string $key, float $default): float
    {
        $stmt = $this->pdo->prepare("SELECT default_value FROM platform_sustainability_factors WHERE factor_key = :factor_key AND status = 'active' LIMIT 1");
        $stmt->execute(['factor_key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (float) $value;
    }

    /** @return array<string, mixed> */
    private function aggregateImpacts(): array
    {
        $row = $this->pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN repair_score >= 50 THEN 1 ELSE 0 END) AS objects_saved, COALESCE(SUM(co2e_avoided_kg), 0) AS co2e, COALESCE(SUM(waste_diverted_kg), 0) AS waste, COALESCE(SUM(material_saved_kg), 0) AS material FROM platform_repair_impact_records WHERE status IN ('calculated','accepted','published_internal')")->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'accepted_repairs' => (int) ($row['total'] ?? 0),
            'objects_saved' => (int) ($row['objects_saved'] ?? 0),
            'co2e_avoided_kg' => round((float) ($row['co2e'] ?? 0), 3),
            'waste_diverted_kg' => round((float) ($row['waste'] ?? 0), 3),
            'material_saved_kg' => round((float) ($row['material'] ?? 0), 3),
        ];
    }

    /** @return array<string, mixed> */
    private function createInsight(string $type, ?string $entityType, ?string $entityId, string $severity, string $title, string $summary, string $recommendedAction, array $metadata, ?string $userId): array
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_repair_outcome_insights (id, insight_type, related_entity_type, related_entity_id, severity, status, title, summary, recommended_action, metadata_json, created_by, created_at, updated_at, resolved_at) VALUES (:id, :insight_type, :related_entity_type, :related_entity_id, :severity, :status, :title, :summary, :recommended_action, :metadata_json, :created_by, :created_at, :updated_at, :resolved_at)');
        $stmt->execute([
            'id' => $id,
            'insight_type' => $type,
            'related_entity_type' => $entityType,
            'related_entity_id' => $entityId,
            'severity' => $severity,
            'status' => 'open',
            'title' => $title,
            'summary' => $summary,
            'recommended_action' => $recommendedAction,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'resolved_at' => null,
        ]);
        if ($severity === 'warning') {
            $this->createReviewItem($entityType ?? 'repair_outcome_insight', $entityId ?? $id, 'medium', $title, $userId);
        }
        return $this->outcomeInsights('all', 1)[0];
    }

    private function createReviewItem(string $entityType, ?string $entityId, string $priority, string $reason, ?string $userId): void
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_impact_review_items (id, related_entity_type, related_entity_id, status, priority, review_reason, decision, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :related_entity_type, :related_entity_id, :status, :priority, :review_reason, :decision, :notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'related_entity_type' => $entityType,
            'related_entity_id' => $entityId ?? 'system',
            'status' => 'open',
            'priority' => $priority,
            'review_reason' => $reason,
            'decision' => null,
            'notes' => null,
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
    }

    private function count(string $table, ?string $where = null): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM {$table}" . ($where ? " WHERE {$where}" : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function sum(string $table, string $column, ?string $where = null): float
    {
        if (!$this->tableExists($table)) {
            return 0.0;
        }
        $sql = "SELECT COALESCE(SUM({$column}), 0) FROM {$table}" . ($where ? " WHERE {$where}" : '');
        return round((float) $this->pdo->query($sql)->fetchColumn(), 3);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function decodeJson(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata, ?string $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_sustainability_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }
}
