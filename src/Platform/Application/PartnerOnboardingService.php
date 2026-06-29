<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class PartnerOnboardingService
{
    /** @var list<string> */
    private array $partnerTypes = ['provider', 'maker', 'enterprise', 'public_sector'];

    /** @var list<string> */
    private array $partnerStatuses = ['prospect', 'onboarding', 'active', 'paused', 'offboarded'];

    /** @var list<string> */
    private array $tiers = ['pilot', 'standard', 'strategic'];

    /** @var list<string> */
    private array $taskStatuses = ['pending', 'in_progress', 'completed', 'waived', 'blocked'];

    /** @var list<string> */
    private array $agreementStatuses = ['draft', 'sent', 'accepted', 'expired', 'rejected'];

    /** @var list<string> */
    private array $integrationStatuses = ['planned', 'testing', 'active', 'paused', 'failed'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $partners = $this->partners('active_or_onboarding', 30);
        $latestReviews = $this->readinessReviews(10);

        return [
            'partner_onboarding_version' => 'partner_onboarding_enterprise_governance_v1_step27',
            'generated_at' => gmdate('c'),
            'summary' => $this->summary(),
            'partners' => $partners,
            'onboarding_tasks' => $this->tasks('all', 30),
            'agreements' => $this->agreements('all', 30),
            'integrations' => $this->integrations('all', 30),
            'latest_readiness_reviews' => $latestReviews,
            'operator_actions' => $this->operatorActions(),
            'important_notes' => [
                'Partner onboarding records are pilot governance records, not signed legal contracts.',
                'Real provider payouts, enterprise APIs and maker economy flows remain disabled until contracts, privacy and payment controls are approved.',
                'A partner can be ready for local pilot while still blocked for production use.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'partners_total' => $this->count('platform_partner_accounts'),
            'partners_onboarding' => $this->count('platform_partner_accounts', "status = 'onboarding'"),
            'partners_active' => $this->count('platform_partner_accounts', "status = 'active'"),
            'enterprise_partners' => $this->count('platform_partner_accounts', "partner_type = 'enterprise'"),
            'provider_partners' => $this->count('platform_partner_accounts', "partner_type = 'provider'"),
            'maker_partners' => $this->count('platform_partner_accounts', "partner_type = 'maker'"),
            'open_required_tasks' => $this->count('platform_partner_onboarding_tasks', "required = 1 AND status NOT IN ('completed', 'waived')"),
            'accepted_agreements' => $this->count('platform_partner_agreements', "status = 'accepted'"),
            'active_integrations' => $this->count('platform_partner_integrations', "status = 'active'"),
            'ready_reviews' => $this->count('platform_partner_readiness_reviews', "status = 'ready_for_pilot'"),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function partners(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active_or_onboarding') {
            $stmt = $this->pdo->prepare("SELECT * FROM platform_partner_accounts WHERE status IN ('onboarding', 'active', 'prospect') ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'onboarding' THEN 2 ELSE 3 END, updated_at DESC LIMIT :limit");
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizePartner'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if (in_array($status, $this->partnerStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_partner_accounts WHERE status = :status ORDER BY updated_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizePartner'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT * FROM platform_partner_accounts ORDER BY updated_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizePartner'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createPartner(array $body, ?string $userId): array
    {
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['name' => ['name is required.']]);
        }

        $type = strtolower(trim((string) ($body['partner_type'] ?? 'provider')));
        if (!in_array($type, $this->partnerTypes, true)) {
            throw new ValidationException(['partner_type' => ['partner_type must be provider, maker, enterprise or public_sector.']]);
        }

        $tier = strtolower(trim((string) ($body['tier'] ?? 'pilot')));
        if (!in_array($tier, $this->tiers, true)) {
            throw new ValidationException(['tier' => ['tier must be pilot, standard or strategic.']]);
        }

        $status = strtolower(trim((string) ($body['status'] ?? 'prospect')));
        if (!in_array($status, $this->partnerStatuses, true)) {
            throw new ValidationException(['status' => ['status must be prospect, onboarding, active, paused or offboarded.']]);
        }

        $risk = strtolower(trim((string) ($body['risk_level'] ?? ($type === 'enterprise' ? 'high' : 'medium'))));
        if (!in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            throw new ValidationException(['risk_level' => ['risk_level must be low, medium, high or critical.']]);
        }

        $id = Uuid::v4();
        $code = strtoupper(trim((string) ($body['partner_code'] ?? '')));
        if ($code === '') {
            $slug = trim((string) preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)), '-');
            $code = 'PARTNER-' . ($slug !== '' ? $slug : 'NEW') . '-' . strtoupper(substr($id, 0, 8));
        }

        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_partner_accounts (id, partner_code, name, partner_type, status, tier, country, contact_name, contact_email, readiness_score, risk_level, notes, created_by, created_at, updated_at) VALUES (:id, :partner_code, :name, :partner_type, :status, :tier, :country, :contact_name, :contact_email, :readiness_score, :risk_level, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'partner_code' => $code,
            'name' => $name,
            'partner_type' => $type,
            'status' => $status,
            'tier' => $tier,
            'country' => strtoupper(trim((string) ($body['country'] ?? 'IT'))) ?: 'IT',
            'contact_name' => trim((string) ($body['contact_name'] ?? '')) ?: null,
            'contact_email' => trim((string) ($body['contact_email'] ?? '')) ?: null,
            'readiness_score' => 0,
            'risk_level' => $risk,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->createDefaultTasks($id, $type, $userId);
        $this->createDefaultAgreement($id, $type, $userId);

        return $this->partner($id);
    }

    /** @return array<string, mixed> */
    public function partner(string $idOrCode): array
    {
        return $this->requirePartner($idOrCode);
    }

    /** @return list<array<string, mixed>> */
    public function tasks(string $status = 'all', int $limit = 50, ?string $partnerId = null): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT t.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_onboarding_tasks t JOIN platform_partner_accounts p ON p.id = t.partner_id';
        $where = [];
        $params = [];
        if ($partnerId !== null) {
            $where[] = 't.partner_id = :partner_id';
            $params['partner_id'] = $partnerId;
        }
        if (in_array($status, $this->taskStatuses, true)) {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY t.required DESC, CASE t.status WHEN 'blocked' THEN 1 WHEN 'pending' THEN 2 WHEN 'in_progress' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END, t.due_at ASC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeTask'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function updateTaskStatus(string $taskId, array $body, ?string $userId): array
    {
        $task = $this->requireTask($taskId);
        $status = strtolower(trim((string) ($body['status'] ?? 'completed')));
        if (!in_array($status, $this->taskStatuses, true)) {
            throw new ValidationException(['status' => ['status must be pending, in_progress, completed, waived or blocked.']]);
        }
        $now = gmdate('c');
        $completedAt = in_array($status, ['completed', 'waived'], true) ? $now : null;
        $evidence = trim((string) ($body['evidence'] ?? $task['evidence'] ?? '')) ?: null;
        $stmt = $this->pdo->prepare('UPDATE platform_partner_onboarding_tasks SET status = :status, evidence = :evidence, completed_at = :completed_at, completed_by = :completed_by, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'evidence' => $evidence,
            'completed_at' => $completedAt,
            'completed_by' => in_array($status, ['completed', 'waived'], true) ? $userId : null,
            'updated_at' => $now,
            'id' => $task['id'],
        ]);

        $this->refreshPartnerScore((string) $task['partner_id']);
        return $this->requireTask((string) $task['id']);
    }

    /** @return list<array<string, mixed>> */
    public function agreements(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT a.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_agreements a JOIN platform_partner_accounts p ON p.id = a.partner_id';
        $params = [];
        if (in_array($status, $this->agreementStatuses, true)) {
            $sql .= ' WHERE a.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY a.updated_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAgreement'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createAgreement(string $partnerIdOrCode, array $body, ?string $userId): array
    {
        $partner = $this->requirePartner($partnerIdOrCode);
        $type = trim((string) ($body['agreement_type'] ?? 'pilot_terms')) ?: 'pilot_terms';
        $title = trim((string) ($body['title'] ?? 'Pilot partner agreement')) ?: 'Pilot partner agreement';
        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_partner_agreements (id, partner_id, agreement_type, title, version, status, owner_role, signed_at, expires_at, notes, created_by, created_at, updated_at) VALUES (:id, :partner_id, :agreement_type, :title, :version, :status, :owner_role, :signed_at, :expires_at, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'partner_id' => $partner['id'],
            'agreement_type' => $type,
            'title' => $title,
            'version' => trim((string) ($body['version'] ?? 'v1')) ?: 'v1',
            'status' => 'draft',
            'owner_role' => trim((string) ($body['owner_role'] ?? 'admin')) ?: 'admin',
            'signed_at' => null,
            'expires_at' => trim((string) ($body['expires_at'] ?? '')) ?: null,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->requireAgreement($id);
    }

    /** @return array<string, mixed> */
    public function updateAgreementStatus(string $agreementId, array $body, ?string $userId): array
    {
        $agreement = $this->requireAgreement($agreementId);
        $status = strtolower(trim((string) ($body['status'] ?? 'accepted')));
        if (!in_array($status, $this->agreementStatuses, true)) {
            throw new ValidationException(['status' => ['status must be draft, sent, accepted, expired or rejected.']]);
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_partner_agreements SET status = :status, signed_at = :signed_at, notes = :notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'signed_at' => $status === 'accepted' ? $now : $agreement['signed_at'],
            'notes' => trim((string) ($body['notes'] ?? $agreement['notes'] ?? '')) ?: null,
            'updated_at' => $now,
            'id' => $agreement['id'],
        ]);
        $this->refreshPartnerScore((string) $agreement['partner_id']);
        return $this->requireAgreement((string) $agreement['id']);
    }

    /** @return list<array<string, mixed>> */
    public function integrations(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT i.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_integrations i JOIN platform_partner_accounts p ON p.id = i.partner_id';
        $params = [];
        if (in_array($status, $this->integrationStatuses, true)) {
            $sql .= ' WHERE i.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY i.updated_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeIntegration'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createIntegration(string $partnerIdOrCode, array $body, ?string $userId): array
    {
        $partner = $this->requirePartner($partnerIdOrCode);
        $integrationType = trim((string) ($body['integration_type'] ?? 'manual')) ?: 'manual';
        $name = trim((string) ($body['name'] ?? 'Manual pilot workflow')) ?: 'Manual pilot workflow';
        $scopes = is_array($body['scopes'] ?? null) ? array_values(array_map('strval', $body['scopes'])) : ['pilot_feedback'];
        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_partner_integrations (id, partner_id, integration_type, name, status, environment, scopes_json, last_checked_at, notes, created_by, created_at, updated_at) VALUES (:id, :partner_id, :integration_type, :name, :status, :environment, :scopes_json, :last_checked_at, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'partner_id' => $partner['id'],
            'integration_type' => $integrationType,
            'name' => $name,
            'status' => 'planned',
            'environment' => trim((string) ($body['environment'] ?? 'local_pilot')) ?: 'local_pilot',
            'scopes_json' => json_encode($scopes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'last_checked_at' => null,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->requireIntegration($id);
    }

    /** @return array<string, mixed> */
    public function updateIntegrationStatus(string $integrationId, array $body, ?string $userId): array
    {
        $integration = $this->requireIntegration($integrationId);
        $status = strtolower(trim((string) ($body['status'] ?? 'testing')));
        if (!in_array($status, $this->integrationStatuses, true)) {
            throw new ValidationException(['status' => ['status must be planned, testing, active, paused or failed.']]);
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_partner_integrations SET status = :status, last_checked_at = :last_checked_at, notes = :notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'last_checked_at' => $now,
            'notes' => trim((string) ($body['notes'] ?? $integration['notes'] ?? '')) ?: null,
            'updated_at' => $now,
            'id' => $integration['id'],
        ]);
        $this->refreshPartnerScore((string) $integration['partner_id']);
        return $this->requireIntegration((string) $integration['id']);
    }

    /** @return array<string, mixed> */
    public function evaluatePartnerReadiness(string $partnerIdOrCode, ?string $userId): array
    {
        $partner = $this->requirePartner($partnerIdOrCode);
        $gates = $this->partnerReadinessGates((string) $partner['id'], (string) $partner['partner_type']);
        $required = array_values(array_filter($gates, static fn (array $gate): bool => (bool) $gate['required']));
        $passed = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'passed'));
        $failed = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'failed'));
        $warnings = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'warning'));
        $score = count($required) > 0 ? (int) floor((count($passed) / count($required)) * 100) : 0;
        $status = $failed !== [] ? 'blocked' : ($warnings !== [] ? 'conditional' : 'ready_for_pilot');

        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_partner_readiness_reviews (id, partner_id, status, readiness_score, gates_json, notes, reviewed_by, reviewed_at, created_at) VALUES (:id, :partner_id, :status, :readiness_score, :gates_json, :notes, :reviewed_by, :reviewed_at, :created_at)');
        $stmt->execute([
            'id' => $id,
            'partner_id' => $partner['id'],
            'status' => $status,
            'readiness_score' => $score,
            'gates_json' => json_encode($gates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'notes' => sprintf('Step 27 readiness evaluation: %s.', $status),
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'created_at' => $now,
        ]);

        $newPartnerStatus = $status === 'ready_for_pilot' ? 'active' : ($partner['status'] === 'prospect' ? 'onboarding' : $partner['status']);
        $stmt = $this->pdo->prepare('UPDATE platform_partner_accounts SET readiness_score = :score, status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'score' => $score,
            'status' => $newPartnerStatus,
            'updated_at' => $now,
            'id' => $partner['id'],
        ]);

        return $this->readinessReview($id);
    }

    /** @return list<array<string, mixed>> */
    public function readinessReviews(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT r.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_readiness_reviews r JOIN platform_partner_accounts p ON p.id = r.partner_id ORDER BY r.reviewed_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeReview'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function readinessReview(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_readiness_reviews r JOIN platform_partner_accounts p ON p.id = r.partner_id WHERE r.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Partner readiness review not found.');
        }
        return $this->normalizeReview($row);
    }

    /** @return array<string, mixed> */
    public function partnerReadiness(string $partnerIdOrCode): array
    {
        $partner = $this->requirePartner($partnerIdOrCode);
        $gates = $this->partnerReadinessGates((string) $partner['id'], (string) $partner['partner_type']);
        return [
            'partner' => $partner,
            'generated_at' => gmdate('c'),
            'gates' => $gates,
            'latest_review' => $this->latestReadinessReview((string) $partner['id']),
        ];
    }

    /** @return list<string> */
    private function operatorActions(): array
    {
        $actions = [];
        $openTasks = $this->count('platform_partner_onboarding_tasks', "required = 1 AND status NOT IN ('completed', 'waived')");
        if ($openTasks > 0) {
            $actions[] = sprintf('Complete or explicitly waive %d required partner onboarding task(s).', $openTasks);
        }
        if ($this->count('platform_partner_agreements', "status NOT IN ('accepted', 'expired')") > 0) {
            $actions[] = 'Review draft/sent partner agreements before activating production-like workflows.';
        }
        if ($this->count('platform_partner_integrations', "status IN ('planned', 'testing')") > 0) {
            $actions[] = 'Keep integrations in manual/testing mode until privacy, security and SLA evidence are present.';
        }
        if ($actions === []) {
            $actions[] = 'Partner onboarding governance is clean for a local pilot review.';
        }
        return $actions;
    }

    private function refreshPartnerScore(string $partnerId): void
    {
        $gates = $this->partnerReadinessGates($partnerId, (string) ($this->requirePartner($partnerId)['partner_type'] ?? 'provider'));
        $required = array_values(array_filter($gates, static fn (array $gate): bool => (bool) $gate['required']));
        $passed = array_values(array_filter($required, static fn (array $gate): bool => $gate['status'] === 'passed'));
        $score = count($required) > 0 ? (int) floor((count($passed) / count($required)) * 100) : 0;
        $stmt = $this->pdo->prepare('UPDATE platform_partner_accounts SET readiness_score = :score, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['score' => $score, 'updated_at' => gmdate('c'), 'id' => $partnerId]);
    }

    /** @return list<array<string, mixed>> */
    private function partnerReadinessGates(string $partnerId, string $partnerType): array
    {
        $requiredTasks = $this->count('platform_partner_onboarding_tasks', "partner_id = " . $this->pdo->quote($partnerId) . " AND required = 1");
        $completedTasks = $this->count('platform_partner_onboarding_tasks', "partner_id = " . $this->pdo->quote($partnerId) . " AND required = 1 AND status IN ('completed', 'waived')");
        $acceptedAgreements = $this->count('platform_partner_agreements', "partner_id = " . $this->pdo->quote($partnerId) . " AND status = 'accepted'");
        $manualOrTestingIntegration = $this->count('platform_partner_integrations', "partner_id = " . $this->pdo->quote($partnerId) . " AND status IN ('testing', 'active')");

        $gates = [
            [
                'gate_key' => 'required_tasks_complete',
                'name' => 'Required onboarding tasks completed or waived',
                'status' => $requiredTasks > 0 && $completedTasks >= $requiredTasks ? 'passed' : 'failed',
                'required' => true,
                'evidence' => ['required_tasks' => $requiredTasks, 'completed_or_waived' => $completedTasks],
            ],
            [
                'gate_key' => 'agreement_accepted',
                'name' => 'At least one relevant partner agreement accepted',
                'status' => $acceptedAgreements > 0 ? 'passed' : 'warning',
                'required' => true,
                'evidence' => ['accepted_agreements' => $acceptedAgreements],
            ],
            [
                'gate_key' => 'pilot_integration_path',
                'name' => 'Pilot-safe integration or manual workflow available',
                'status' => $manualOrTestingIntegration > 0 ? 'passed' : 'warning',
                'required' => true,
                'evidence' => ['testing_or_active_integrations' => $manualOrTestingIntegration],
            ],
            [
                'gate_key' => 'production_boundary',
                'name' => 'Production boundary explicitly limited during pilot',
                'status' => $partnerType === 'enterprise' ? 'warning' : 'passed',
                'required' => false,
                'evidence' => ['partner_type' => $partnerType, 'note' => 'Enterprise partners require manual production data boundary review.'],
            ],
        ];

        return $gates;
    }

    private function createDefaultTasks(string $partnerId, string $partnerType, ?string $userId): void
    {
        $templates = [
            ['profile_verified', 'Verify partner profile and operating scope', 'profile', 1],
            ['privacy_notice_accepted', 'Accept privacy notice and data processing scope', 'privacy', 1],
            ['quality_policy_acknowledged', 'Acknowledge quality, evidence and trust policy', 'quality', 1],
            ['sla_contact_ready', 'Confirm operational contact and escalation path', 'operations', 1],
        ];
        if ($partnerType === 'enterprise') {
            $templates[] = ['pilot_use_case_documented', 'Document enterprise pilot use case and data boundary', 'discovery', 1];
        }
        if ($partnerType === 'maker') {
            $templates[] = ['ip_terms_acknowledged', 'Acknowledge model contribution and IP terms draft', 'legal', 1];
        }

        $now = gmdate('c');
        foreach ($templates as [$key, $name, $category, $required]) {
            $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO platform_partner_onboarding_tasks (id, partner_id, task_key, name, category, status, required, evidence, due_at, created_at, updated_at) VALUES (:id, :partner_id, :task_key, :name, :category, :status, :required, :evidence, :due_at, :created_at, :updated_at)');
            $stmt->execute([
                'id' => Uuid::v4(),
                'partner_id' => $partnerId,
                'task_key' => $key,
                'name' => $name,
                'category' => $category,
                'status' => 'pending',
                'required' => $required,
                'evidence' => null,
                'due_at' => gmdate('c', time() + 14 * 86400),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function createDefaultAgreement(string $partnerId, string $partnerType, ?string $userId): void
    {
        $type = $partnerType === 'enterprise' ? 'data_processing' : ($partnerType === 'maker' ? 'ip_terms' : 'provider_terms');
        $title = $partnerType === 'enterprise' ? 'Pilot data processing boundary' : ($partnerType === 'maker' ? 'Maker contribution and IP terms draft' : 'Pilot provider participation terms');
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_partner_agreements (id, partner_id, agreement_type, title, version, status, owner_role, signed_at, expires_at, notes, created_by, created_at, updated_at) VALUES (:id, :partner_id, :agreement_type, :title, :version, :status, :owner_role, :signed_at, :expires_at, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'partner_id' => $partnerId,
            'agreement_type' => $type,
            'title' => $title,
            'version' => 'v1',
            'status' => 'draft',
            'owner_role' => 'admin',
            'signed_at' => null,
            'expires_at' => null,
            'notes' => 'Generated by Step 27 partner onboarding governance.',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function latestReadinessReview(string $partnerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_readiness_reviews r JOIN platform_partner_accounts p ON p.id = r.partner_id WHERE r.partner_id = :partner_id ORDER BY r.reviewed_at DESC LIMIT 1');
        $stmt->execute(['partner_id' => $partnerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeReview($row) : null;
    }

    /** @return array<string, mixed> */
    private function requirePartner(string $idOrCode): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_partner_accounts WHERE id = :id OR partner_code = :id LIMIT 1');
        $stmt->execute(['id' => $idOrCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Partner account not found.');
        }
        return $this->normalizePartner($row);
    }

    /** @return array<string, mixed> */
    private function requireTask(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT t.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_onboarding_tasks t JOIN platform_partner_accounts p ON p.id = t.partner_id WHERE t.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Partner onboarding task not found.');
        }
        return $this->normalizeTask($row);
    }

    /** @return array<string, mixed> */
    private function requireAgreement(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_agreements a JOIN platform_partner_accounts p ON p.id = a.partner_id WHERE a.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Partner agreement not found.');
        }
        return $this->normalizeAgreement($row);
    }

    /** @return array<string, mixed> */
    private function requireIntegration(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT i.*, p.partner_code, p.name AS partner_name, p.partner_type FROM platform_partner_integrations i JOIN platform_partner_accounts p ON p.id = i.partner_id WHERE i.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Partner integration not found.');
        }
        return $this->normalizeIntegration($row);
    }

    private function count(string $table, string $where = ''): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePartner(array $row): array
    {
        $row['readiness_score'] = (int) ($row['readiness_score'] ?? 0);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeTask(array $row): array
    {
        $row['required'] = (bool) ((int) ($row['required'] ?? 0));
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeAgreement(array $row): array
    {
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeIntegration(array $row): array
    {
        $row['scopes'] = json_decode((string) ($row['scopes_json'] ?? '[]'), true) ?: [];
        unset($row['scopes_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeReview(array $row): array
    {
        $row['readiness_score'] = (int) ($row['readiness_score'] ?? 0);
        $row['gates'] = json_decode((string) ($row['gates_json'] ?? '[]'), true) ?: [];
        unset($row['gates_json']);
        return $row;
    }
}
