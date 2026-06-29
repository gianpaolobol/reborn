<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class GeometryPrintabilityService
{
    /** @var list<string> */
    private array $supportedFormats = ['stl', 'obj', 'step', 'stp', '3mf', 'amf'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'summary' => [
                'assets_total' => $this->count('platform_geometry_assets'),
                'assets_submitted' => $this->count('platform_geometry_assets', "status IN ('submitted', 'needs_fix')"),
                'validation_runs' => $this->count('platform_geometry_validation_runs'),
                'approved_runs' => $this->count('platform_geometry_validation_runs', "decision = 'approved'"),
                'review_required_runs' => $this->count('platform_geometry_validation_runs', "decision = 'review_required'"),
                'blocked_runs' => $this->count('platform_geometry_validation_runs', "decision = 'blocked'"),
                'open_findings' => $this->count('platform_printability_findings', "status = 'open'"),
                'open_reviews' => $this->count('platform_geometry_review_items', "status IN ('open', 'in_review')"),
            ],
            'policy' => [
                'purpose' => 'Prevent AI-generated or uploaded geometry from entering provider routing without basic validation and human review.',
                'supported_formats' => $this->supportedFormats,
                'default_decision' => 'review_required',
                'out_of_scope' => [
                    'real mesh repair',
                    'real slicer simulation',
                    'certified engineering validation',
                    'production CAD kernel checks',
                ],
            ],
            'latest_assets' => $this->geometryAssets('all', 5),
            'latest_runs' => $this->validationRuns('all', 5),
            'latest_findings' => $this->findings('open', 5),
            'latest_reviews' => $this->reviews('active', 5),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function profiles(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_geometry_validation_profiles';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY target_process, material_family, name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'normalizeProfile'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function rules(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_printability_rules';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE severity WHEN \'critical\' THEN 1 WHEN \'warning\' THEN 2 ELSE 3 END, category, name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function geometryAssets(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_geometry_assets';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAsset'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createGeometryAsset(array $body, ?string $userId): array
    {
        $fileName = trim((string) ($body['file_name'] ?? ''));
        if ($fileName === '') {
            throw new ValidationException(['file_name' => ['file_name is required.']]);
        }

        $format = strtolower(trim((string) ($body['file_format'] ?? pathinfo($fileName, PATHINFO_EXTENSION))));
        if ($format === '') {
            throw new ValidationException(['file_format' => ['file_format is required or must be inferable from file_name.']]);
        }

        $bbox = $this->sanitizeBoundingBox($body['bounding_box_mm'] ?? []);
        $id = Uuid::v4();
        $now = gmdate('c');
        $assetCode = 'GEO-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));

        $stmt = $this->pdo->prepare('INSERT INTO platform_geometry_assets (id, asset_code, source_type, source_ref, file_name, file_format, repair_case_id, model_asset_id, ai_job_id, status, declared_units, bounding_box_mm, estimated_volume_cm3, estimated_surface_cm2, metadata_json, created_by, created_at, updated_at) VALUES (:id, :asset_code, :source_type, :source_ref, :file_name, :file_format, :repair_case_id, :model_asset_id, :ai_job_id, :status, :declared_units, :bounding_box_mm, :estimated_volume_cm3, :estimated_surface_cm2, :metadata_json, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'asset_code' => $assetCode,
            'source_type' => trim((string) ($body['source_type'] ?? 'manual_upload_stub')) ?: 'manual_upload_stub',
            'source_ref' => trim((string) ($body['source_ref'] ?? '')) ?: null,
            'file_name' => $fileName,
            'file_format' => $format,
            'repair_case_id' => trim((string) ($body['repair_case_id'] ?? '')) ?: null,
            'model_asset_id' => trim((string) ($body['model_asset_id'] ?? '')) ?: null,
            'ai_job_id' => trim((string) ($body['ai_job_id'] ?? '')) ?: null,
            'status' => 'submitted',
            'declared_units' => trim((string) ($body['declared_units'] ?? 'mm')) ?: 'mm',
            'bounding_box_mm' => json_encode($bbox, JSON_THROW_ON_ERROR),
            'estimated_volume_cm3' => max(0, (float) ($body['estimated_volume_cm3'] ?? 0)),
            'estimated_surface_cm2' => max(0, (float) ($body['estimated_surface_cm2'] ?? 0)),
            'metadata_json' => json_encode($body['metadata'] ?? ['created_from' => 'step32_console'], JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('geometry_asset_created', 'geometry_asset', $id, sprintf('Geometry asset %s registered for validation.', $assetCode), ['file_format' => $format], $userId);

        return $this->requireAsset($id);
    }

    /** @return array<string, mixed> */
    public function evaluateGeometryAsset(string $id, array $body, ?string $userId): array
    {
        $asset = $this->requireAsset($id);
        $profileKey = trim((string) ($body['profile_key'] ?? 'fdm_pla_petg_standard'));
        $profile = $this->requireProfile($profileKey);
        $rules = $this->rules('active');
        $bbox = $asset['bounding_box_mm'];
        $maxBox = $profile['max_bounding_box_mm'];
        $formatOk = in_array(strtolower((string) $asset['file_format']), $this->supportedFormats, true);
        $fitsBox = ((float) $bbox['x'] <= (float) $maxBox['x']) && ((float) $bbox['y'] <= (float) $maxBox['y']) && ((float) $bbox['z'] <= (float) $maxBox['z']);
        $volume = (float) $asset['estimated_volume_cm3'];
        $surface = (float) $asset['estimated_surface_cm2'];
        $thinWallRisk = strtolower((string) ($body['thin_wall_risk'] ?? ($volume > 0 && $volume < 3 ? 'high' : 'medium')));
        $humanReviewRequired = (bool) $profile['requires_human_review'] || in_array($asset['source_type'], ['ai_artifact_stub', 'manual_upload_stub', 'maker_submission'], true);

        $score = 100;
        $findings = [];
        if (!$formatOk) {
            $score -= 45;
            $findings[] = ['rule_key' => 'supported_file_format', 'severity' => 'critical', 'message' => 'File format is not supported by pilot validation.', 'recommendation' => 'Request STL, OBJ, STEP, STP, 3MF or AMF before provider routing.'];
        }
        if (!$fitsBox) {
            $score -= 35;
            $findings[] = ['rule_key' => 'machine_bounding_box', 'severity' => 'critical', 'message' => 'Bounding box exceeds the selected pilot machine profile.', 'recommendation' => 'Scale, split or route to a provider with a larger build volume.'];
        }
        if ($thinWallRisk === 'high' || $volume < 3) {
            $score -= 20;
            $findings[] = ['rule_key' => 'minimum_wall_thickness', 'severity' => 'warning', 'message' => 'Possible thin wall or fragile functional area detected from declared dimensions/volume.', 'recommendation' => 'Human reviewer should check wall thickness and reinforce the model if needed.'];
        }
        if ($humanReviewRequired) {
            $score -= 10;
            $findings[] = ['rule_key' => 'human_review_required', 'severity' => 'warning', 'message' => 'Human review is required before provider routing or public maker download.', 'recommendation' => 'Assign geometry review and capture decision evidence.'];
        }

        $score = max(0, $score);
        $hasCritical = count(array_filter($findings, static fn (array $finding): bool => $finding['severity'] === 'critical')) > 0;
        $decision = $hasCritical ? 'blocked' : ($humanReviewRequired || $findings !== [] ? 'review_required' : 'approved');
        $checks = [
            'format_ok' => $formatOk,
            'fits_build_volume' => $fitsBox,
            'human_review_required' => $humanReviewRequired,
            'thin_wall_risk' => $thinWallRisk,
            'profile_key' => $profile['profile_key'],
            'bounding_box_mm' => $bbox,
            'max_bounding_box_mm' => $maxBox,
        ];

        $runId = Uuid::v4();
        $now = gmdate('c');
        $runCode = 'GEO-RUN-' . strtoupper(substr(str_replace('-', '', $runId), 0, 10));
        $summary = sprintf('Geometry validation completed with decision %s and score %d.', $decision, $score);

        $stmt = $this->pdo->prepare('INSERT INTO platform_geometry_validation_runs (id, run_code, geometry_asset_id, profile_id, status, decision, score, checks_json, summary, evaluated_by, evaluated_at, created_at, updated_at) VALUES (:id, :run_code, :geometry_asset_id, :profile_id, :status, :decision, :score, :checks_json, :summary, :evaluated_by, :evaluated_at, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $runId,
            'run_code' => $runCode,
            'geometry_asset_id' => $asset['id'],
            'profile_id' => $profile['id'],
            'status' => 'completed',
            'decision' => $decision,
            'score' => $score,
            'checks_json' => json_encode($checks, JSON_THROW_ON_ERROR),
            'summary' => $summary,
            'evaluated_by' => $userId,
            'evaluated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($findings as $finding) {
            $rule = $this->requireRule($finding['rule_key']);
            $findingId = Uuid::v4();
            $stmt = $this->pdo->prepare('INSERT INTO platform_printability_findings (id, validation_run_id, rule_id, severity, status, message, location_hint, recommendation, created_at, resolved_at) VALUES (:id, :validation_run_id, :rule_id, :severity, :status, :message, :location_hint, :recommendation, :created_at, :resolved_at)');
            $stmt->execute([
                'id' => $findingId,
                'validation_run_id' => $runId,
                'rule_id' => $rule['id'],
                'severity' => $finding['severity'],
                'status' => 'open',
                'message' => $finding['message'],
                'location_hint' => $finding['location_hint'] ?? null,
                'recommendation' => $finding['recommendation'],
                'created_at' => $now,
                'resolved_at' => null,
            ]);
        }

        $newStatus = $decision === 'approved' ? 'validated' : ($decision === 'blocked' ? 'blocked' : 'needs_review');
        $stmt = $this->pdo->prepare('UPDATE platform_geometry_assets SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $newStatus, 'updated_at' => $now, 'id' => $asset['id']]);

        $review = null;
        if ($decision !== 'approved') {
            $review = $this->createReview($asset['id'], $runId, $hasCritical ? 'high' : 'medium', $userId);
        }

        $this->audit('geometry_asset_evaluated', 'geometry_validation_run', $runId, $summary, ['decision' => $decision, 'score' => $score], $userId);

        return [
            'validation_run' => $this->requireValidationRun($runId),
            'findings' => $this->findingsForRun($runId),
            'review_item' => $review,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function validationRuns(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*, a.asset_code, a.file_name, p.profile_key, p.name AS profile_name FROM platform_geometry_validation_runs r JOIN platform_geometry_assets a ON a.id = r.geometry_asset_id JOIN platform_geometry_validation_profiles p ON p.id = r.profile_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE r.decision = :status OR r.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeValidationRun'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function findings(string $status = 'open', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT f.*, r.rule_key, r.name AS rule_name, v.run_code, a.asset_code, a.file_name FROM platform_printability_findings f JOIN platform_printability_rules r ON r.id = f.rule_id JOIN platform_geometry_validation_runs v ON v.id = f.validation_run_id JOIN platform_geometry_assets a ON a.id = v.geometry_asset_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE f.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE f.severity WHEN \'critical\' THEN 1 WHEN \'warning\' THEN 2 ELSE 3 END, f.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function findingsForRun(string $runId): array
    {
        $stmt = $this->pdo->prepare('SELECT f.*, r.rule_key, r.name AS rule_name FROM platform_printability_findings f JOIN platform_printability_rules r ON r.id = f.rule_id WHERE f.validation_run_id = :run_id ORDER BY CASE f.severity WHEN \'critical\' THEN 1 WHEN \'warning\' THEN 2 ELSE 3 END, f.created_at DESC');
        $stmt->execute(['run_id' => $runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function reviews(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT ri.*, a.asset_code, a.file_name, v.run_code FROM platform_geometry_review_items ri JOIN platform_geometry_assets a ON a.id = ri.geometry_asset_id LEFT JOIN platform_geometry_validation_runs v ON v.id = ri.validation_run_id';
        $params = [];
        if ($status === 'active') {
            $sql .= " WHERE ri.status IN ('open', 'in_review')";
        } elseif ($status !== 'all') {
            $sql .= ' WHERE ri.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE ri.priority WHEN \'high\' THEN 1 WHEN \'medium\' THEN 2 ELSE 3 END, ri.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function reviewGeometry(string $id, array $body, ?string $userId): array
    {
        $review = $this->requireReview($id);
        $decision = strtolower(trim((string) ($body['decision'] ?? 'approved_with_notes')));
        if (!in_array($decision, ['approved', 'approved_with_notes', 'needs_fix', 'rejected'], true)) {
            throw new ValidationException(['decision' => ['decision must be approved, approved_with_notes, needs_fix or rejected.']]);
        }
        $notes = trim((string) ($body['notes'] ?? 'Human geometry review completed from Step 32 console.'));
        $now = gmdate('c');
        $status = in_array($decision, ['approved', 'approved_with_notes'], true) ? 'closed' : 'closed';
        $assetStatus = match ($decision) {
            'approved', 'approved_with_notes' => 'validated',
            'needs_fix' => 'needs_fix',
            default => 'rejected',
        };

        $stmt = $this->pdo->prepare('UPDATE platform_geometry_review_items SET status = :status, decision = :decision, notes = :notes, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'decision' => $decision,
            'notes' => $notes,
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);

        $stmt = $this->pdo->prepare('UPDATE platform_geometry_assets SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $assetStatus, 'updated_at' => $now, 'id' => $review['geometry_asset_id']]);

        $this->audit('geometry_review_completed', 'geometry_review_item', $id, sprintf('Geometry review completed with decision %s.', $decision), ['asset_status' => $assetStatus], $userId);

        return $this->requireReview($id);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_geometry_governance_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static function (array $row): array {
            $row['metadata'] = json_decode($row['metadata_json'] ?: '{}', true);
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function createReview(string $assetId, string $runId, string $priority, ?string $userId): array
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_geometry_review_items (id, geometry_asset_id, validation_run_id, status, review_type, priority, assigned_to, decision, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :geometry_asset_id, :validation_run_id, :status, :review_type, :priority, :assigned_to, :decision, :notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'geometry_asset_id' => $assetId,
            'validation_run_id' => $runId,
            'status' => 'open',
            'review_type' => 'human_geometry_review',
            'priority' => $priority,
            'assigned_to' => null,
            'decision' => null,
            'notes' => 'Review generated automatically by Step 32 validation governance.',
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        return $this->requireReview($id);
    }

    /** @return array<string, float> */
    private function sanitizeBoundingBox(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }
        return [
            'x' => max(0.0, (float) ($value['x'] ?? 80)),
            'y' => max(0.0, (float) ($value['y'] ?? 40)),
            'z' => max(0.0, (float) ($value['z'] ?? 20)),
        ];
    }

    /** @return array<string, mixed> */
    private function requireAsset(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_geometry_assets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['geometry_asset_id' => ['Geometry asset not found.']]);
        }
        return $this->normalizeAsset($row);
    }

    /** @return array<string, mixed> */
    private function requireProfile(string $profileKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_geometry_validation_profiles WHERE profile_key = :profile_key AND status = \'active\'');
        $stmt->execute(['profile_key' => $profileKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['profile_key' => ['Active validation profile not found.']]);
        }
        return $this->normalizeProfile($row);
    }

    /** @return array<string, mixed> */
    private function requireRule(string $ruleKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_printability_rules WHERE rule_key = :rule_key');
        $stmt->execute(['rule_key' => $ruleKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['rule_key' => ['Printability rule not found.']]);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireValidationRun(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, a.asset_code, a.file_name, p.profile_key, p.name AS profile_name FROM platform_geometry_validation_runs r JOIN platform_geometry_assets a ON a.id = r.geometry_asset_id JOIN platform_geometry_validation_profiles p ON p.id = r.profile_id WHERE r.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['validation_run_id' => ['Geometry validation run not found.']]);
        }
        return $this->normalizeValidationRun($row);
    }

    /** @return array<string, mixed> */
    private function requireReview(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT ri.*, a.asset_code, a.file_name, v.run_code FROM platform_geometry_review_items ri JOIN platform_geometry_assets a ON a.id = ri.geometry_asset_id LEFT JOIN platform_geometry_validation_runs v ON v.id = ri.validation_run_id WHERE ri.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['review_id' => ['Geometry review item not found.']]);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeProfile(array $row): array
    {
        $row['min_wall_thickness_mm'] = (float) $row['min_wall_thickness_mm'];
        $row['min_feature_size_mm'] = (float) $row['min_feature_size_mm'];
        $row['requires_human_review'] = (bool) $row['requires_human_review'];
        $row['max_bounding_box_mm'] = json_decode($row['max_bounding_box_mm'] ?: '{}', true) ?: [];
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeAsset(array $row): array
    {
        $row['estimated_volume_cm3'] = (float) $row['estimated_volume_cm3'];
        $row['estimated_surface_cm2'] = (float) $row['estimated_surface_cm2'];
        $row['bounding_box_mm'] = json_decode($row['bounding_box_mm'] ?: '{}', true) ?: [];
        $row['metadata'] = json_decode($row['metadata_json'] ?: '{}', true) ?: [];
        unset($row['metadata_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeValidationRun(array $row): array
    {
        $row['score'] = (int) $row['score'];
        $row['checks'] = json_decode($row['checks_json'] ?: '{}', true) ?: [];
        unset($row['checks_json']);
        return $row;
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_geometry_governance_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
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
