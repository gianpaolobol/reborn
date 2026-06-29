<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class ReleaseManagementService
{
    /** @var list<string> */
    private array $flagStatuses = ['enabled', 'disabled', 'beta'];

    /** @var list<string> */
    private array $releaseStatuses = ['draft', 'evaluating', 'approved', 'blocked', 'deployed', 'cancelled'];

    /** @var list<string> */
    private array $releaseDecisions = ['approve', 'block', 'deploy', 'cancel'];

    /** @var list<string> */
    private array $cohortStatuses = ['draft', 'recruiting', 'active', 'paused', 'completed'];

    /** @var list<string> */
    private array $participantStatuses = ['invited', 'active', 'paused', 'completed', 'removed'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $latestRelease = $this->latestRelease();
        $readiness = $this->betaReadiness();

        return [
            'release_management_version' => 'beta_release_management_pilot_readiness_v1_step26',
            'generated_at' => gmdate('c'),
            'summary' => $this->summary(),
            'beta_readiness' => $readiness,
            'feature_flags' => $this->featureFlags('all'),
            'releases' => $this->releases('active', 20),
            'latest_release' => $latestRelease,
            'latest_release_gates' => $latestRelease ? $this->releaseGates((string) $latestRelease['id']) : [],
            'pilot_cohorts' => $this->pilotCohorts('all'),
            'pilot_participants' => $this->pilotParticipants('all', 20),
            'recent_decisions' => $this->releaseDecisions(20),
            'operator_actions' => $this->operatorActions($readiness, $latestRelease),
            'important_notes' => [
                'Feature flags are local/pilot controls, not a production rollout service.',
                'Pilot cohort records are operational planning records; they do not replace signed provider or user agreements.',
                'Release gates use currently available local evidence and must be reviewed manually before real beta launch.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function betaReadiness(): array
    {
        $gates = $this->computeReadinessGates();
        $required = array_values(array_filter($gates, static fn (array $gate): bool => (bool) $gate['required']));
        $passed = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'passed'));
        $warnings = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'warning'));
        $failed = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'failed'));

        $status = $failed !== [] ? 'blocked' : ($warnings !== [] ? 'conditional' : 'ready_for_local_beta');

        return [
            'status' => $status,
            'generated_at' => gmdate('c'),
            'required_gate_count' => count($required),
            'passed_required_count' => count($passed),
            'warning_required_count' => count($warnings),
            'failed_required_count' => count($failed),
            'completion_percentage' => count($required) > 0 ? (int) floor((count($passed) / count($required)) * 100) : 0,
            'gates' => $gates,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function featureFlags(string $status = 'all'): array
    {
        if (in_array($status, $this->flagStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_feature_flags WHERE status = :status ORDER BY scope ASC, flag_key ASC');
            $stmt->execute(['status' => $status]);
            return array_map([$this, 'normalizeFeatureFlag'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->query("SELECT * FROM platform_feature_flags ORDER BY CASE status WHEN 'enabled' THEN 1 WHEN 'beta' THEN 2 ELSE 3 END, scope ASC, flag_key ASC");
        return array_map([$this, 'normalizeFeatureFlag'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function updateFeatureFlag(string $idOrKey, array $body, ?string $userId): array
    {
        $flag = $this->requireFeatureFlag($idOrKey);
        $status = strtolower(trim((string) ($body['status'] ?? $flag['status'])));
        if (!in_array($status, $this->flagStatuses, true)) {
            throw new ValidationException(['status' => ['status must be enabled, disabled or beta.']]);
        }

        $rollout = array_key_exists('rollout_percentage', $body) ? (int) $body['rollout_percentage'] : (int) $flag['rollout_percentage'];
        $rollout = max(0, min(100, $rollout));
        $defaultState = array_key_exists('default_state', $body) ? ((bool) $body['default_state'] ? 1 : 0) : ((bool) $flag['default_state'] ? 1 : 0);
        $notes = trim((string) ($body['notes'] ?? ''));
        $dependencies = $flag['dependencies'];
        if (array_key_exists('dependencies', $body) && is_array($body['dependencies'])) {
            $dependencies = array_values(array_map('strval', $body['dependencies']));
        }

        $stmt = $this->pdo->prepare('UPDATE platform_feature_flags SET status = :status, rollout_percentage = :rollout_percentage, default_state = :default_state, dependencies_json = :dependencies_json, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'rollout_percentage' => $rollout,
            'default_state' => $defaultState,
            'dependencies_json' => json_encode($dependencies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => gmdate('c'),
            'id' => $flag['id'],
        ]);

        if ($notes !== '') {
            $this->recordAuditDecision(null, 'feature_flag_update', sprintf('Feature flag %s set to %s by %s. %s', $flag['flag_key'], $status, $userId ?: 'system', $notes), $userId);
        }

        return $this->requireFeatureFlag((string) $flag['id']);
    }

    /** @return list<array<string, mixed>> */
    public function releases(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("SELECT * FROM platform_releases WHERE status IN ('draft', 'evaluating', 'approved', 'blocked') ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeRelease'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if (in_array($status, $this->releaseStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_releases WHERE status = :status ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeRelease'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT * FROM platform_releases ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeRelease'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createRelease(array $body, ?string $userId): array
    {
        $title = trim((string) ($body['title'] ?? 'Controlled pilot release'));
        if ($title === '') {
            throw new ValidationException(['title' => ['title is required.']]);
        }

        $version = trim((string) ($body['version'] ?? '0.26.0'));
        $releaseCode = strtoupper(trim((string) ($body['release_code'] ?? '')));
        if ($releaseCode === '') {
            $releaseCode = 'REL-' . strtoupper(gmdate('Ymd-His'));
        }

        $risk = strtolower(trim((string) ($body['risk_level'] ?? 'medium')));
        if (!in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            throw new ValidationException(['risk_level' => ['risk_level must be low, medium, high or critical.']]);
        }

        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_releases (id, release_code, title, version, status, target_environment, release_type, scope, risk_level, notes, scheduled_at, created_by, created_at, updated_at) VALUES (:id, :release_code, :title, :version, :status, :target_environment, :release_type, :scope, :risk_level, :notes, :scheduled_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'release_code' => $releaseCode,
            'title' => $title,
            'version' => $version,
            'status' => 'draft',
            'target_environment' => trim((string) ($body['target_environment'] ?? 'local_pilot')) ?: 'local_pilot',
            'release_type' => trim((string) ($body['release_type'] ?? 'beta_readiness')) ?: 'beta_readiness',
            'scope' => trim((string) ($body['scope'] ?? 'platform')) ?: 'platform',
            'risk_level' => $risk,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'scheduled_at' => trim((string) ($body['scheduled_at'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->release($id);
    }

    /** @return array<string, mixed> */
    public function release(string $idOrCode): array
    {
        return $this->requireRelease($idOrCode);
    }

    /** @return array<string, mixed> */
    public function evaluateReleaseGates(string $releaseIdOrCode, ?string $userId): array
    {
        $release = $this->requireRelease($releaseIdOrCode);
        $gates = $this->computeReadinessGates();
        $now = gmdate('c');
        $stored = [];

        foreach ($gates as $gate) {
            $id = $this->existingGateId((string) $release['id'], (string) $gate['gate_key']) ?: Uuid::v4();
            $stmt = $this->pdo->prepare('INSERT INTO platform_release_gates (id, release_id, gate_key, name, status, required, evidence_json, evaluated_at, evaluated_by, created_at, updated_at) VALUES (:id, :release_id, :gate_key, :name, :status, :required, :evidence_json, :evaluated_at, :evaluated_by, :created_at, :updated_at) ON CONFLICT(release_id, gate_key) DO UPDATE SET name = excluded.name, status = excluded.status, required = excluded.required, evidence_json = excluded.evidence_json, evaluated_at = excluded.evaluated_at, evaluated_by = excluded.evaluated_by, updated_at = excluded.updated_at');
            $stmt->execute([
                'id' => $id,
                'release_id' => $release['id'],
                'gate_key' => $gate['gate_key'],
                'name' => $gate['name'],
                'status' => $gate['status'],
                'required' => $gate['required'] ? 1 : 0,
                'evidence_json' => json_encode($gate['evidence'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'evaluated_at' => $now,
                'evaluated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $stored[] = $gate;
        }

        $required = array_values(array_filter($stored, static fn (array $gate): bool => (bool) $gate['required']));
        $failed = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'failed'));
        $warnings = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'warning'));
        $nextStatus = $failed !== [] ? 'blocked' : 'evaluating';
        if ($warnings === [] && $failed === []) {
            $nextStatus = 'evaluating';
        }

        $this->pdo->prepare('UPDATE platform_releases SET status = :status, updated_at = :updated_at WHERE id = :id')->execute([
            'status' => $nextStatus,
            'updated_at' => $now,
            'id' => $release['id'],
        ]);

        return [
            'release' => $this->requireRelease((string) $release['id']),
            'evaluated_at' => $now,
            'gate_count' => count($stored),
            'required_gate_count' => count($required),
            'failed_required_count' => count($failed),
            'warning_required_count' => count($warnings),
            'gates' => $this->releaseGates((string) $release['id']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function releaseGates(string $releaseIdOrCode): array
    {
        $release = $this->requireRelease($releaseIdOrCode);
        $stmt = $this->pdo->prepare("SELECT * FROM platform_release_gates WHERE release_id = :release_id ORDER BY required DESC, CASE status WHEN 'failed' THEN 1 WHEN 'warning' THEN 2 WHEN 'pending' THEN 3 ELSE 4 END, gate_key ASC");
        $stmt->execute(['release_id' => $release['id']]);
        return array_map([$this, 'normalizeReleaseGate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function decideRelease(string $releaseIdOrCode, array $body, ?string $userId): array
    {
        $release = $this->requireRelease($releaseIdOrCode);
        $decision = strtolower(trim((string) ($body['decision'] ?? 'approve')));
        if (!in_array($decision, $this->releaseDecisions, true)) {
            throw new ValidationException(['decision' => ['decision must be approve, block, deploy or cancel.']]);
        }

        $rationale = trim((string) ($body['rationale'] ?? 'Manual release decision from Step 26 console.'));
        if ($rationale === '') {
            throw new ValidationException(['rationale' => ['rationale is required.']]);
        }

        $now = gmdate('c');
        $nextStatus = match ($decision) {
            'approve' => 'approved',
            'block' => 'blocked',
            'deploy' => 'deployed',
            'cancel' => 'cancelled',
        };

        $stmt = $this->pdo->prepare('INSERT INTO platform_release_decisions (id, release_id, decision, rationale, decided_by, decided_at, created_at) VALUES (:id, :release_id, :decision, :rationale, :decided_by, :decided_at, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'release_id' => $release['id'],
            'decision' => $decision,
            'rationale' => $rationale,
            'decided_by' => $userId,
            'decided_at' => $now,
            'created_at' => $now,
        ]);

        $update = ['status' => $nextStatus, 'updated_at' => $now, 'id' => $release['id']];
        $sql = 'UPDATE platform_releases SET status = :status, updated_at = :updated_at';
        if ($decision === 'approve') {
            $sql .= ', approved_by = :approved_by, approved_at = :approved_at';
            $update['approved_by'] = $userId;
            $update['approved_at'] = $now;
        }
        if ($decision === 'deploy') {
            $sql .= ', deployed_at = :deployed_at';
            $update['deployed_at'] = $now;
        }
        $sql .= ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($update);

        return [
            'release' => $this->requireRelease((string) $release['id']),
            'decision' => $this->latestReleaseDecision((string) $release['id']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function releaseDecisions(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT d.*, r.release_code, r.title AS release_title, r.version AS release_version FROM platform_release_decisions d JOIN platform_releases r ON r.id = d.release_id ORDER BY d.decided_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeReleaseDecision'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function pilotCohorts(string $status = 'all'): array
    {
        if (in_array($status, $this->cohortStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_pilot_cohorts WHERE status = :status ORDER BY target_persona ASC, name ASC');
            $stmt->execute(['status' => $status]);
            return array_map([$this, 'normalizePilotCohort'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->query("SELECT * FROM platform_pilot_cohorts ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'recruiting' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END, target_persona ASC, name ASC");
        return array_map([$this, 'normalizePilotCohort'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function updatePilotCohort(string $idOrCode, array $body, ?string $userId): array
    {
        $cohort = $this->requirePilotCohort($idOrCode);
        $status = strtolower(trim((string) ($body['status'] ?? $cohort['status'])));
        if (!in_array($status, $this->cohortStatuses, true)) {
            throw new ValidationException(['status' => ['status must be draft, recruiting, active, paused or completed.']]);
        }

        $stmt = $this->pdo->prepare('UPDATE platform_pilot_cohorts SET status = :status, notes = COALESCE(:notes, notes), updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'updated_at' => gmdate('c'),
            'id' => $cohort['id'],
        ]);

        if ($userId !== null) {
            $this->recordAuditDecision(null, 'pilot_cohort_update', sprintf('Pilot cohort %s set to %s.', $cohort['cohort_code'], $status), $userId);
        }

        return $this->requirePilotCohort((string) $cohort['id']);
    }

    /** @return list<array<string, mixed>> */
    public function pilotParticipants(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if (in_array($status, $this->participantStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT p.*, c.cohort_code, c.name AS cohort_name, c.target_persona FROM platform_pilot_participants p JOIN platform_pilot_cohorts c ON c.id = p.cohort_id WHERE p.status = :status ORDER BY p.created_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizePilotParticipant'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT p.*, c.cohort_code, c.name AS cohort_name, c.target_persona FROM platform_pilot_participants p JOIN platform_pilot_cohorts c ON c.id = p.cohort_id ORDER BY p.created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizePilotParticipant'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function addPilotParticipant(array $body, ?string $userId): array
    {
        $cohortId = trim((string) ($body['cohort_id'] ?? ''));
        if ($cohortId === '') {
            $cohortCode = trim((string) ($body['cohort_code'] ?? 'PILOT-BOLOGNA-REPAIR-USERS'));
            $cohort = $this->requirePilotCohort($cohortCode);
        } else {
            $cohort = $this->requirePilotCohort($cohortId);
        }

        $displayName = trim((string) ($body['display_name'] ?? 'Pilot participant'));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['email' => ['A valid email is required.']]);
        }

        $role = trim((string) ($body['role'] ?? $cohort['target_persona']));
        $status = strtolower(trim((string) ($body['status'] ?? 'invited')));
        if (!in_array($status, $this->participantStatuses, true)) {
            throw new ValidationException(['status' => ['status must be invited, active, paused, completed or removed.']]);
        }

        $consentStatus = strtolower(trim((string) ($body['consent_status'] ?? 'pending')));
        if (!in_array($consentStatus, ['pending', 'granted', 'withdrawn', 'not_required'], true)) {
            throw new ValidationException(['consent_status' => ['consent_status must be pending, granted, withdrawn or not_required.']]);
        }

        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_pilot_participants (id, cohort_id, display_name, email, role, status, source, consent_status, onboarding_state, notes, joined_at, created_by, created_at, updated_at) VALUES (:id, :cohort_id, :display_name, :email, :role, :status, :source, :consent_status, :onboarding_state, :notes, :joined_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'cohort_id' => $cohort['id'],
            'display_name' => $displayName,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'source' => trim((string) ($body['source'] ?? 'admin_console')) ?: 'admin_console',
            'consent_status' => $consentStatus,
            'onboarding_state' => trim((string) ($body['onboarding_state'] ?? 'not_started')) ?: 'not_started',
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'joined_at' => $status === 'active' ? $now : null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->requirePilotParticipant($id);
    }

    /** @return array<string, mixed> */
    public function updatePilotParticipant(string $id, array $body, ?string $userId): array
    {
        $participant = $this->requirePilotParticipant($id);
        $status = strtolower(trim((string) ($body['status'] ?? $participant['status'])));
        if (!in_array($status, $this->participantStatuses, true)) {
            throw new ValidationException(['status' => ['status must be invited, active, paused, completed or removed.']]);
        }

        $consentStatus = strtolower(trim((string) ($body['consent_status'] ?? $participant['consent_status'])));
        if (!in_array($consentStatus, ['pending', 'granted', 'withdrawn', 'not_required'], true)) {
            throw new ValidationException(['consent_status' => ['consent_status must be pending, granted, withdrawn or not_required.']]);
        }

        $onboarding = trim((string) ($body['onboarding_state'] ?? $participant['onboarding_state'])) ?: (string) $participant['onboarding_state'];
        $now = gmdate('c');
        $joinedAt = $participant['joined_at'];
        if ($status === 'active' && ($joinedAt === null || $joinedAt === '')) {
            $joinedAt = $now;
        }

        $stmt = $this->pdo->prepare('UPDATE platform_pilot_participants SET status = :status, consent_status = :consent_status, onboarding_state = :onboarding_state, notes = COALESCE(:notes, notes), joined_at = :joined_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'consent_status' => $consentStatus,
            'onboarding_state' => $onboarding,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'joined_at' => $joinedAt,
            'updated_at' => $now,
            'id' => $participant['id'],
        ]);

        if ($userId !== null) {
            $this->recordAuditDecision(null, 'pilot_participant_update', sprintf('Pilot participant %s set to %s.', $participant['email'], $status), $userId);
        }

        return $this->requirePilotParticipant((string) $participant['id']);
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        return [
            'feature_flags_total' => $this->count('platform_feature_flags'),
            'feature_flags_enabled' => $this->countWhere('platform_feature_flags', "status = 'enabled'"),
            'feature_flags_beta' => $this->countWhere('platform_feature_flags', "status = 'beta'"),
            'releases_active' => $this->countWhere('platform_releases', "status IN ('draft', 'evaluating', 'approved', 'blocked')"),
            'releases_deployed' => $this->countWhere('platform_releases', "status = 'deployed'"),
            'pilot_cohorts' => $this->count('platform_pilot_cohorts'),
            'active_pilot_participants' => $this->countWhere('platform_pilot_participants', "status = 'active'"),
            'pending_pilot_consents' => $this->countWhere('platform_pilot_participants', "consent_status = 'pending'"),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function computeReadinessGates(): array
    {
        $readinessStatus = $this->latestReadinessStatus();
        $openCriticalIncidents = $this->safeCountWhere('platform_incidents', "status != 'resolved' AND severity = 'critical'");
        $openHighIncidents = $this->safeCountWhere('platform_incidents', "status != 'resolved' AND severity = 'high'");
        $activeSlaBreaches = $this->safeCountWhere('platform_sla_evaluations', "status = 'breached'");
        $openDsrs = $this->safeCountWhere('platform_data_subject_requests', "status IN ('open', 'in_review')");
        $privacyNotices = $this->safeCountWhere('platform_privacy_notices', "status IN ('active', 'draft')");
        $processingRecords = $this->safeCountWhere('platform_data_processing_records', "status IN ('active', 'draft')");
        $recentBackup = $this->latestCompletedBackupAt();
        $backupHours = $recentBackup ? max(0, (int) floor((time() - strtotime($recentBackup)) / 3600)) : null;
        $activeCohorts = $this->safeCountWhere('platform_pilot_cohorts', "status IN ('recruiting', 'active')");
        $cohorts = $this->safeCountWhere('platform_pilot_cohorts', "status IN ('draft', 'recruiting', 'active')");
        $enabledFlags = $this->safeCountWhere('platform_feature_flags', "status IN ('enabled', 'beta')");
        $dangerousFlags = $this->safeCountWhere('platform_feature_flags', "flag_key IN ('real_ai_recognition', 'ai_3d_generation', 'real_payments', 'maker_economy') AND status != 'disabled'");
        $activePolicies = $this->safeCountWhere('platform_operational_policies', "status = 'active'");
        $retentionRules = $this->safeCountWhere('platform_retention_rules', "enabled = 1");

        return [
            $this->gate('readiness_status', 'Production readiness is acceptable', in_array($readinessStatus, ['ready', 'degraded'], true) ? 'passed' : 'failed', true, [
                'latest_readiness_status' => $readinessStatus,
                'accepted_values' => ['ready', 'degraded'],
            ]),
            $this->gate('backup_freshness', 'Recent SQLite backup exists', ($backupHours !== null && $backupHours <= 24) ? 'passed' : 'warning', true, [
                'latest_completed_backup_at' => $recentBackup,
                'backup_age_hours' => $backupHours,
                'pilot_threshold_hours' => 24,
            ]),
            $this->gate('critical_incidents_zero', 'No open critical incidents', $openCriticalIncidents === 0 ? 'passed' : 'failed', true, [
                'open_critical_incidents' => $openCriticalIncidents,
                'open_high_incidents' => $openHighIncidents,
            ]),
            $this->gate('sla_breaches_zero', 'No active SLA breaches', $activeSlaBreaches === 0 ? 'passed' : 'warning', true, [
                'active_sla_breaches' => $activeSlaBreaches,
            ]),
            $this->gate('privacy_records_present', 'Privacy and processing records exist', ($privacyNotices > 0 && $processingRecords > 0 && $retentionRules > 0) ? 'passed' : 'failed', true, [
                'privacy_notices' => $privacyNotices,
                'processing_records' => $processingRecords,
                'enabled_retention_rules' => $retentionRules,
            ]),
            $this->gate('data_subject_requests_controlled', 'Data subject requests are under control', $openDsrs === 0 ? 'passed' : 'warning', true, [
                'open_data_subject_requests' => $openDsrs,
            ]),
            $this->gate('risky_features_disabled', 'Production-risk features stay disabled', $dangerousFlags === 0 ? 'passed' : 'failed', true, [
                'unsafe_enabled_flags' => $dangerousFlags,
                'expected_disabled_flags' => ['real_ai_recognition', 'ai_3d_generation', 'real_payments', 'maker_economy'],
            ]),
            $this->gate('pilot_cohorts_defined', 'Pilot cohorts are defined', $cohorts > 0 ? ($activeCohorts > 0 ? 'passed' : 'warning') : 'failed', false, [
                'pilot_cohorts_defined' => $cohorts,
                'active_or_recruiting_cohorts' => $activeCohorts,
            ]),
            $this->gate('feature_flags_defined', 'Feature flags are configured', $enabledFlags > 0 ? 'passed' : 'warning', false, [
                'enabled_or_beta_flags' => $enabledFlags,
            ]),
            $this->gate('operational_policies_active', 'Operational policies are active', $activePolicies > 0 ? 'passed' : 'warning', false, [
                'active_operational_policies' => $activePolicies,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function gate(string $key, string $name, string $status, bool $required, array $evidence): array
    {
        return [
            'gate_key' => $key,
            'name' => $name,
            'status' => $status,
            'required' => $required,
            'evidence' => $evidence,
        ];
    }

    /** @return list<string> */
    private function operatorActions(array $readiness, ?array $latestRelease): array
    {
        $actions = [];
        if (($readiness['status'] ?? '') === 'blocked') {
            $actions[] = 'Do not start beta: resolve failed release gates first.';
        }
        if (($readiness['status'] ?? '') === 'conditional') {
            $actions[] = 'Review warning gates and document manual acceptance before beta/demo.';
        }
        if ($latestRelease === null) {
            $actions[] = 'Create a release record before a pilot or investor demo.';
        } elseif (($latestRelease['status'] ?? '') === 'draft') {
            $actions[] = 'Evaluate release gates for the latest draft release.';
        } elseif (($latestRelease['status'] ?? '') === 'blocked') {
            $actions[] = 'Unblock latest release by fixing failed gates or recording a block rationale.';
        }
        if ($this->safeCountWhere('platform_pilot_cohorts', "status IN ('recruiting', 'active')") === 0) {
            $actions[] = 'Move one pilot cohort from draft to recruiting when ready to collect real feedback.';
        }
        if ($this->safeCountWhere('platform_pilot_participants', "consent_status = 'pending'") > 0) {
            $actions[] = 'Resolve pending pilot participant consent status before activating the cohort.';
        }
        return $actions ?: ['Release management is configured. Keep gates evaluated before every demo/beta milestone.'];
    }

    /** @return array<string, mixed>|null */
    private function latestRelease(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM platform_releases ORDER BY created_at DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeRelease($row) : null;
    }

    /** @return array<string, mixed>|null */
    private function latestReleaseDecision(string $releaseId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT d.*, r.release_code, r.title AS release_title, r.version AS release_version FROM platform_release_decisions d JOIN platform_releases r ON r.id = d.release_id WHERE d.release_id = :release_id ORDER BY d.decided_at DESC LIMIT 1');
        $stmt->execute(['release_id' => $releaseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeReleaseDecision($row) : null;
    }

    private function existingGateId(string $releaseId, string $gateKey): ?string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM platform_release_gates WHERE release_id = :release_id AND gate_key = :gate_key LIMIT 1');
        $stmt->execute(['release_id' => $releaseId, 'gate_key' => $gateKey]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function latestReadinessStatus(): string
    {
        try {
            $status = $this->pdo->query('SELECT status FROM platform_readiness_snapshots ORDER BY created_at DESC LIMIT 1')->fetchColumn();
            return is_string($status) && $status !== '' ? $status : 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function latestCompletedBackupAt(): ?string
    {
        try {
            $value = $this->pdo->query("SELECT created_at FROM platform_backup_runs WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
            return is_string($value) && $value !== '' ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function count(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function countWhere(string $table, string $where): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where)->fetchColumn();
    }

    private function safeCountWhere(string $table, string $where): int
    {
        try {
            return $this->countWhere($table, $where);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string, mixed> */
    private function requireFeatureFlag(string $idOrKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_feature_flags WHERE id = :value OR flag_key = :value LIMIT 1');
        $stmt->execute(['value' => $idOrKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Feature flag not found.');
        }
        return $this->normalizeFeatureFlag($row);
    }

    /** @return array<string, mixed> */
    private function requireRelease(string $idOrCode): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_releases WHERE id = :value OR release_code = :value LIMIT 1');
        $stmt->execute(['value' => $idOrCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Release not found.');
        }
        return $this->normalizeRelease($row);
    }

    /** @return array<string, mixed> */
    private function requirePilotCohort(string $idOrCode): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_pilot_cohorts WHERE id = :value OR cohort_code = :value LIMIT 1');
        $stmt->execute(['value' => $idOrCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Pilot cohort not found.');
        }
        return $this->normalizePilotCohort($row);
    }

    /** @return array<string, mixed> */
    private function requirePilotParticipant(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, c.cohort_code, c.name AS cohort_name, c.target_persona FROM platform_pilot_participants p JOIN platform_pilot_cohorts c ON c.id = p.cohort_id WHERE p.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Pilot participant not found.');
        }
        return $this->normalizePilotParticipant($row);
    }

    private function recordAuditDecision(?string $releaseId, string $decision, string $rationale, ?string $userId): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO platform_release_decisions (id, release_id, decision, rationale, decided_by, decided_at, created_at) VALUES (:id, :release_id, :decision, :rationale, :decided_by, :decided_at, :created_at)');
            $now = gmdate('c');
            $targetRelease = $releaseId ?: ($this->latestRelease()['id'] ?? 'release-local-beta-readiness-v1');
            $stmt->execute([
                'id' => Uuid::v4(),
                'release_id' => $targetRelease,
                'decision' => $decision,
                'rationale' => $rationale,
                'decided_by' => $userId,
                'decided_at' => $now,
                'created_at' => $now,
            ]);
        } catch (\Throwable) {
            // Audit helper must never break the control action.
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeFeatureFlag(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'flag_key' => (string) $row['flag_key'],
            'name' => (string) $row['name'],
            'scope' => (string) $row['scope'],
            'status' => (string) $row['status'],
            'default_state' => ((int) $row['default_state']) === 1,
            'rollout_percentage' => (int) $row['rollout_percentage'],
            'owner_role' => (string) $row['owner_role'],
            'description' => $row['description'] ?? null,
            'dependencies' => $this->jsonList($row['dependencies_json'] ?? '[]'),
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRelease(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'release_code' => (string) $row['release_code'],
            'title' => (string) $row['title'],
            'version' => (string) $row['version'],
            'status' => (string) $row['status'],
            'target_environment' => (string) $row['target_environment'],
            'release_type' => (string) $row['release_type'],
            'scope' => (string) $row['scope'],
            'risk_level' => (string) $row['risk_level'],
            'notes' => $row['notes'] ?? null,
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'approved_by' => $row['approved_by'] ?? null,
            'approved_at' => $row['approved_at'] ?? null,
            'deployed_at' => $row['deployed_at'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeReleaseGate(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'release_id' => (string) $row['release_id'],
            'gate_key' => (string) $row['gate_key'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'required' => ((int) $row['required']) === 1,
            'evidence' => $this->jsonArray($row['evidence_json'] ?? '{}'),
            'evaluated_at' => $row['evaluated_at'] ?? null,
            'evaluated_by' => $row['evaluated_by'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeReleaseDecision(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'release_id' => (string) $row['release_id'],
            'release_code' => (string) ($row['release_code'] ?? ''),
            'release_title' => (string) ($row['release_title'] ?? ''),
            'release_version' => (string) ($row['release_version'] ?? ''),
            'decision' => (string) $row['decision'],
            'rationale' => (string) $row['rationale'],
            'decided_by' => $row['decided_by'] ?? null,
            'decided_at' => (string) $row['decided_at'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePilotCohort(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'cohort_code' => (string) $row['cohort_code'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'target_persona' => (string) $row['target_persona'],
            'size_limit' => (int) $row['size_limit'],
            'admission_criteria' => $this->jsonList($row['admission_criteria_json'] ?? '[]'),
            'exit_criteria' => $this->jsonList($row['exit_criteria_json'] ?? '[]'),
            'notes' => $row['notes'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePilotParticipant(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'cohort_id' => (string) $row['cohort_id'],
            'cohort_code' => (string) ($row['cohort_code'] ?? ''),
            'cohort_name' => (string) ($row['cohort_name'] ?? ''),
            'target_persona' => (string) ($row['target_persona'] ?? ''),
            'display_name' => (string) $row['display_name'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'source' => (string) $row['source'],
            'consent_status' => (string) $row['consent_status'],
            'onboarding_state' => (string) $row['onboarding_state'],
            'notes' => $row['notes'] ?? null,
            'joined_at' => $row['joined_at'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return list<string> */
    private function jsonList(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_map('strval', $decoded));
    }

    /** @return array<string, mixed> */
    private function jsonArray(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
