<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class AiPipelineGovernanceService
{
    /** @var list<string> */
    private array $pipelineTypes = ['diagnosis', 'image_recognition', 'model_generation', 'model_validation', 'dataset_labeling'];

    /** @var list<string> */
    private array $runStatuses = ['queued', 'running', 'in_review', 'approved', 'rejected', 'completed', 'failed'];

    /** @var list<string> */
    private array $datasetStatuses = ['candidate', 'approved', 'quarantined', 'retired'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'ai_governance_version' => 'ai_pipeline_governance_human_review_v1_step30',
            'generated_at' => gmdate('c'),
            'summary' => $this->summary(),
            'model_providers' => $this->modelProviders('all'),
            'pipeline_runs' => $this->pipelineRuns('active', 20),
            'pending_reviews' => $this->humanReviews(null, 20),
            'dataset_items' => $this->datasetItems('all', 20),
            'quality_evaluations' => $this->qualityEvaluations(20),
            'safety_rules' => $this->safetyRules('active'),
            'operator_actions' => $this->operatorActions(),
            'important_notes' => [
                'Step 30 governs AI pipeline readiness; it does not call external AI providers or generate real STL files.',
                'Every high-risk or low-confidence AI output remains gated by human-in-the-loop review.',
                'Dataset records are metadata governance items only; no training dataset export is enabled.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'providers_total' => $this->count('platform_ai_model_providers'),
            'providers_mock' => $this->count('platform_ai_model_providers', "status = 'mock'"),
            'pipeline_runs_total' => $this->count('platform_ai_pipeline_runs'),
            'pipeline_runs_active' => $this->count('platform_ai_pipeline_runs', "status IN ('queued', 'running', 'in_review')"),
            'pipeline_runs_pending_review' => $this->count('platform_ai_pipeline_runs', "human_review_required = 1 AND status IN ('queued', 'running', 'in_review')"),
            'pipeline_runs_approved' => $this->count('platform_ai_pipeline_runs', "status IN ('approved', 'completed')"),
            'dataset_items_total' => $this->count('platform_ai_dataset_items'),
            'dataset_items_approved' => $this->count('platform_ai_dataset_items', "status = 'approved'"),
            'quality_evaluations_total' => $this->count('platform_ai_quality_evaluations'),
            'safety_rules_active' => $this->count('platform_ai_safety_rules', "status = 'active'"),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function modelProviders(string $status = 'all'): array
    {
        $sql = 'SELECT * FROM platform_ai_model_providers';
        $params = [];
        if (in_array($status, ['mock', 'active', 'disabled'], true)) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE status WHEN \'active\' THEN 1 WHEN \'mock\' THEN 2 ELSE 3 END, capability, name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'normalizeProvider'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function pipelineRuns(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*, p.name AS provider_name, p.execution_mode FROM platform_ai_pipeline_runs r LEFT JOIN platform_ai_model_providers p ON p.provider_key = r.provider_key';
        $params = [];
        if ($status === 'active') {
            $sql .= " WHERE r.status IN ('queued', 'running', 'in_review')";
        } elseif (in_array($status, $this->runStatuses, true)) {
            $sql .= ' WHERE r.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE r.status WHEN \'in_review\' THEN 1 WHEN \'queued\' THEN 2 WHEN \'running\' THEN 3 ELSE 4 END, r.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizePipelineRun'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createPipelineRun(array $body, ?string $userId): array
    {
        $pipelineType = strtolower(trim((string) ($body['pipeline_type'] ?? 'diagnosis')));
        if (!in_array($pipelineType, $this->pipelineTypes, true)) {
            throw new ValidationException(['pipeline_type' => ['pipeline_type is not supported.']]);
        }

        $providerKey = trim((string) ($body['provider_key'] ?? $this->defaultProviderFor($pipelineType)));
        $this->requireProvider($providerKey);

        $inputSummary = trim((string) ($body['input_summary'] ?? ''));
        if ($inputSummary === '') {
            throw new ValidationException(['input_summary' => ['input_summary is required.']]);
        }

        $status = strtolower(trim((string) ($body['status'] ?? 'queued')));
        if (!in_array($status, $this->runStatuses, true)) {
            throw new ValidationException(['status' => ['status is not supported.']]);
        }

        $confidence = max(0, min(100, (int) ($body['confidence_score'] ?? 0)));
        $riskLevel = strtolower(trim((string) ($body['risk_level'] ?? ($pipelineType === 'model_generation' ? 'high' : 'medium'))));
        if (!in_array($riskLevel, ['low', 'medium', 'high', 'critical'], true)) {
            $riskLevel = 'medium';
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $runCode = 'AI-RUN-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $humanReviewRequired = (bool) ($body['human_review_required'] ?? ($riskLevel !== 'low' || $confidence < 85));

        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_pipeline_runs (id, run_code, pipeline_type, status, provider_key, repair_case_id, source_type, source_ref, input_summary, output_summary, confidence_score, risk_level, human_review_required, reviewed_by, reviewed_at, created_by, created_at, updated_at) VALUES (:id, :run_code, :pipeline_type, :status, :provider_key, :repair_case_id, :source_type, :source_ref, :input_summary, :output_summary, :confidence_score, :risk_level, :human_review_required, :reviewed_by, :reviewed_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'run_code' => $runCode,
            'pipeline_type' => $pipelineType,
            'status' => $status,
            'provider_key' => $providerKey,
            'repair_case_id' => trim((string) ($body['repair_case_id'] ?? '')) ?: null,
            'source_type' => trim((string) ($body['source_type'] ?? 'repair_case')) ?: 'repair_case',
            'source_ref' => trim((string) ($body['source_ref'] ?? '')) ?: null,
            'input_summary' => $inputSummary,
            'output_summary' => trim((string) ($body['output_summary'] ?? '')) ?: null,
            'confidence_score' => $confidence,
            'risk_level' => $riskLevel,
            'human_review_required' => $humanReviewRequired ? 1 : 0,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('ai_pipeline_run_created', 'ai_pipeline_run', $id, sprintf('AI pipeline run created for %s.', $pipelineType), ['provider_key' => $providerKey, 'risk_level' => $riskLevel], $userId);
        return $this->requirePipelineRun($id);
    }

    /** @return array<string, mixed> */
    public function reviewPipelineRun(string $id, array $body, ?string $userId): array
    {
        $run = $this->requirePipelineRun($id);
        $decision = strtolower(trim((string) ($body['decision'] ?? 'approved')));
        if (!in_array($decision, ['approved', 'rejected', 'needs_changes'], true)) {
            throw new ValidationException(['decision' => ['decision must be approved, rejected or needs_changes.']]);
        }
        $qualityScore = max(0, min(100, (int) ($body['quality_score'] ?? 80)));
        $safetyScore = max(0, min(100, (int) ($body['safety_score'] ?? 80)));
        $dimensionalScore = max(0, min(100, (int) ($body['dimensional_score'] ?? 70)));
        $notes = trim((string) ($body['notes'] ?? 'Reviewed from Step 30 console.'));
        if ($notes === '') {
            $notes = 'Reviewed from Step 30 console.';
        }

        $reviewId = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_human_reviews (id, pipeline_run_id, review_type, decision, quality_score, safety_score, dimensional_score, notes, reviewed_by, created_at) VALUES (:id, :pipeline_run_id, :review_type, :decision, :quality_score, :safety_score, :dimensional_score, :notes, :reviewed_by, :created_at)');
        $stmt->execute([
            'id' => $reviewId,
            'pipeline_run_id' => $id,
            'review_type' => trim((string) ($body['review_type'] ?? 'operator_review')) ?: 'operator_review',
            'decision' => $decision,
            'quality_score' => $qualityScore,
            'safety_score' => $safetyScore,
            'dimensional_score' => $dimensionalScore,
            'notes' => $notes,
            'reviewed_by' => $userId,
            'created_at' => $now,
        ]);

        $newStatus = match ($decision) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'in_review',
        };
        $outputSummary = trim((string) ($body['output_summary'] ?? ($run['output_summary'] ?? '')));
        if ($outputSummary === '' && $decision === 'approved') {
            $outputSummary = 'Human review approved the AI output for pilot use.';
        }

        $stmt = $this->pdo->prepare('UPDATE platform_ai_pipeline_runs SET status = :status, output_summary = COALESCE(:output_summary, output_summary), confidence_score = :confidence_score, human_review_required = :human_review_required, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $newStatus,
            'output_summary' => $outputSummary !== '' ? $outputSummary : null,
            'confidence_score' => max((int) ($run['confidence_score'] ?? 0), (int) round(($qualityScore + $safetyScore + $dimensionalScore) / 3)),
            'human_review_required' => $decision === 'needs_changes' ? 1 : 0,
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);

        $this->audit('ai_pipeline_run_reviewed', 'ai_pipeline_run', $id, sprintf('AI pipeline run reviewed as %s.', $decision), ['review_id' => $reviewId], $userId);
        return $this->requirePipelineRun($id);
    }

    /** @return list<array<string, mixed>> */
    public function humanReviews(?string $pipelineRunId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT h.*, r.run_code, r.pipeline_type FROM platform_ai_human_reviews h JOIN platform_ai_pipeline_runs r ON r.id = h.pipeline_run_id';
        $params = [];
        if ($pipelineRunId !== null && $pipelineRunId !== '') {
            $sql .= ' WHERE h.pipeline_run_id = :pipeline_run_id';
            $params['pipeline_run_id'] = $pipelineRunId;
        }
        $sql .= ' ORDER BY h.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeHumanReview'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function datasetItems(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_ai_dataset_items';
        $params = [];
        if (in_array($status, $this->datasetStatuses, true)) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE status WHEN \'approved\' THEN 1 WHEN \'candidate\' THEN 2 ELSE 3 END, created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeDatasetItem'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createDatasetItem(array $body, ?string $userId): array
    {
        $sourceType = trim((string) ($body['source_type'] ?? 'repair_outcome')) ?: 'repair_outcome';
        $sourceRef = trim((string) ($body['source_ref'] ?? ''));
        $objectCategory = trim((string) ($body['object_category'] ?? 'repair_part')) ?: 'repair_part';
        $label = trim((string) ($body['label'] ?? ''));
        if ($sourceRef === '') {
            throw new ValidationException(['source_ref' => ['source_ref is required.']]);
        }
        if ($label === '') {
            throw new ValidationException(['label' => ['label is required.']]);
        }

        $status = strtolower(trim((string) ($body['status'] ?? 'candidate')));
        if (!in_array($status, $this->datasetStatuses, true)) {
            throw new ValidationException(['status' => ['status must be candidate, approved, quarantined or retired.']]);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_dataset_items (id, source_type, source_ref, object_category, label, status, consent_status, license_status, quality_score, metadata_json, created_by, created_at, updated_at) VALUES (:id, :source_type, :source_ref, :object_category, :label, :status, :consent_status, :license_status, :quality_score, :metadata_json, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'object_category' => $objectCategory,
            'label' => $label,
            'status' => $status,
            'consent_status' => trim((string) ($body['consent_status'] ?? 'needs_review')) ?: 'needs_review',
            'license_status' => trim((string) ($body['license_status'] ?? 'needs_review')) ?: 'needs_review',
            'quality_score' => max(0, min(100, (int) ($body['quality_score'] ?? 0))),
            'metadata_json' => json_encode($body['metadata'] ?? ['source' => 'api'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('ai_dataset_item_created', 'ai_dataset_item', $id, sprintf('AI dataset item registered for %s.', $objectCategory), ['status' => $status], $userId);
        return $this->requireDatasetItem($id);
    }

    /** @return array<string, mixed> */
    public function evaluateQuality(array $body, ?string $userId): array
    {
        $pipelineType = strtolower(trim((string) ($body['pipeline_type'] ?? 'diagnosis')));
        if (!in_array($pipelineType, $this->pipelineTypes, true)) {
            throw new ValidationException(['pipeline_type' => ['pipeline_type is not supported.']]);
        }

        $sampleSize = max(1, (int) ($body['sample_size'] ?? max(1, $this->count('platform_ai_pipeline_runs', 'pipeline_type = ' . $this->pdo->quote($pipelineType)))));
        $averageConfidence = (int) $this->scalar('SELECT COALESCE(ROUND(AVG(confidence_score)), 0) FROM platform_ai_pipeline_runs WHERE pipeline_type = :pipeline_type', ['pipeline_type' => $pipelineType]);
        $approved = $this->count('platform_ai_pipeline_runs', 'pipeline_type = ' . $this->pdo->quote($pipelineType) . " AND status IN ('approved', 'completed')");
        $total = max(1, $this->count('platform_ai_pipeline_runs', 'pipeline_type = ' . $this->pdo->quote($pipelineType)));
        $passRate = (int) round(($approved / $total) * 100);
        $status = $passRate >= 80 && $averageConfidence >= 80 ? 'passed' : 'review_required';
        $findings = [];
        if ($sampleSize < 20) {
            $findings[] = 'sample size below pilot confidence threshold';
        }
        if ($averageConfidence < 80) {
            $findings[] = 'average confidence below target';
        }
        if ($passRate < 80) {
            $findings[] = 'approval/completion pass rate below target';
        }
        if ($findings === []) {
            $findings[] = 'no blocking finding in local pilot evaluation';
        }

        $id = Uuid::v4();
        $code = 'AI-EVAL-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_quality_evaluations (id, evaluation_code, pipeline_type, sample_size, pass_rate, average_confidence, status, risk_findings_json, created_by, created_at) VALUES (:id, :evaluation_code, :pipeline_type, :sample_size, :pass_rate, :average_confidence, :status, :risk_findings_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'evaluation_code' => $code,
            'pipeline_type' => $pipelineType,
            'sample_size' => $sampleSize,
            'pass_rate' => $passRate,
            'average_confidence' => $averageConfidence,
            'status' => $status,
            'risk_findings_json' => json_encode($findings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);

        $this->audit('ai_quality_evaluation_created', 'ai_quality_evaluation', $id, sprintf('AI quality evaluation created for %s.', $pipelineType), ['status' => $status, 'pass_rate' => $passRate], $userId);
        return $this->requireQualityEvaluation($id);
    }

    /** @return list<array<string, mixed>> */
    public function qualityEvaluations(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_ai_quality_evaluations ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeQualityEvaluation'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function safetyRules(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_ai_safety_rules';
        $params = [];
        if (in_array($status, ['active', 'draft', 'disabled'], true)) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE severity WHEN \'critical\' THEN 1 WHEN \'high\' THEN 2 WHEN \'medium\' THEN 3 ELSE 4 END, name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'normalizeSafetyRule'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_ai_governance_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAudit'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, string> */
    private function operatorActions(): array
    {
        $pending = $this->count('platform_ai_pipeline_runs', "human_review_required = 1 AND status IN ('queued', 'running', 'in_review')");
        $candidateDataset = $this->count('platform_ai_dataset_items', "status = 'candidate'");
        $qualityReview = $this->count('platform_ai_quality_evaluations', "status = 'review_required'");

        return [
            'review_queue' => $pending > 0 ? sprintf('Review %d AI pipeline run(s) before trusting outputs.', $pending) : 'No AI pipeline runs currently require review.',
            'dataset_governance' => $candidateDataset > 0 ? sprintf('Review %d candidate dataset item(s) for consent/license readiness.', $candidateDataset) : 'No candidate dataset items waiting for governance review.',
            'quality_gate' => $qualityReview > 0 ? sprintf('%d AI quality evaluation(s) require operator review.', $qualityReview) : 'No AI quality evaluation requires immediate action.',
        ];
    }

    /** @return array<string, mixed> */
    private function requireProvider(string $providerKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_ai_model_providers WHERE provider_key = :provider_key LIMIT 1');
        $stmt->execute(['provider_key' => $providerKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('AI provider not found.');
        }
        return $this->normalizeProvider($row);
    }

    /** @return array<string, mixed> */
    private function requirePipelineRun(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, p.name AS provider_name, p.execution_mode FROM platform_ai_pipeline_runs r LEFT JOIN platform_ai_model_providers p ON p.provider_key = r.provider_key WHERE r.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('AI pipeline run not found.');
        }
        return $this->normalizePipelineRun($row);
    }

    /** @return array<string, mixed> */
    private function requireDatasetItem(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_ai_dataset_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('AI dataset item not found.');
        }
        return $this->normalizeDatasetItem($row);
    }

    /** @return array<string, mixed> */
    private function requireQualityEvaluation(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_ai_quality_evaluations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('AI quality evaluation not found.');
        }
        return $this->normalizeQualityEvaluation($row);
    }

    private function defaultProviderFor(string $pipelineType): string
    {
        return match ($pipelineType) {
            'model_generation', 'model_validation' => 'mock_model_generation_engine',
            default => 'mock_recognition_engine',
        };
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $subjectType, ?string $subjectId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_governance_audit_log (id, action, subject_type, subject_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :subject_type, :subject_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'message' => $message,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeProvider(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'provider_key' => (string) $row['provider_key'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'capability' => (string) $row['capability'],
            'execution_mode' => (string) $row['execution_mode'],
            'requires_human_review' => ((int) $row['requires_human_review']) === 1,
            'risk_notes' => $row['risk_notes'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePipelineRun(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'run_code' => (string) $row['run_code'],
            'pipeline_type' => (string) $row['pipeline_type'],
            'status' => (string) $row['status'],
            'provider_key' => (string) $row['provider_key'],
            'provider_name' => $row['provider_name'] ?? null,
            'execution_mode' => $row['execution_mode'] ?? null,
            'repair_case_id' => $row['repair_case_id'] ?? null,
            'source_type' => (string) $row['source_type'],
            'source_ref' => $row['source_ref'] ?? null,
            'input_summary' => (string) $row['input_summary'],
            'output_summary' => $row['output_summary'] ?? null,
            'confidence_score' => (int) $row['confidence_score'],
            'risk_level' => (string) $row['risk_level'],
            'human_review_required' => ((int) $row['human_review_required']) === 1,
            'reviewed_by' => $row['reviewed_by'] ?? null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeHumanReview(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'pipeline_run_id' => (string) $row['pipeline_run_id'],
            'run_code' => $row['run_code'] ?? null,
            'pipeline_type' => $row['pipeline_type'] ?? null,
            'review_type' => (string) $row['review_type'],
            'decision' => (string) $row['decision'],
            'quality_score' => (int) $row['quality_score'],
            'safety_score' => (int) $row['safety_score'],
            'dimensional_score' => (int) $row['dimensional_score'],
            'notes' => (string) $row['notes'],
            'reviewed_by' => $row['reviewed_by'] ?? null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeDatasetItem(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'source_type' => (string) $row['source_type'],
            'source_ref' => (string) $row['source_ref'],
            'object_category' => (string) $row['object_category'],
            'label' => (string) $row['label'],
            'status' => (string) $row['status'],
            'consent_status' => (string) $row['consent_status'],
            'license_status' => (string) $row['license_status'],
            'quality_score' => (int) $row['quality_score'],
            'metadata' => json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [],
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeQualityEvaluation(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'evaluation_code' => (string) $row['evaluation_code'],
            'pipeline_type' => (string) $row['pipeline_type'],
            'sample_size' => (int) $row['sample_size'],
            'pass_rate' => (int) $row['pass_rate'],
            'average_confidence' => (int) $row['average_confidence'],
            'status' => (string) $row['status'],
            'risk_findings' => json_decode((string) ($row['risk_findings_json'] ?? '[]'), true) ?: [],
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeSafetyRule(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'rule_key' => (string) $row['rule_key'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'severity' => (string) $row['severity'],
            'applies_to' => (string) $row['applies_to'],
            'description' => (string) $row['description'],
            'required_action' => (string) $row['required_action'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeAudit(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'action' => (string) $row['action'],
            'subject_type' => (string) $row['subject_type'],
            'subject_id' => $row['subject_id'] ?? null,
            'message' => (string) $row['message'],
            'metadata' => json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [],
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
