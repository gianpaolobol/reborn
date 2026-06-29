<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class PrivacyGovernanceService
{
    /** @var list<string> */
    private array $consentStatuses = ['granted', 'withdrawn', 'expired'];

    /** @var list<string> */
    private array $dsrTypes = ['access', 'erasure', 'rectification', 'restriction', 'portability', 'objection'];

    /** @var list<string> */
    private array $dsrStatuses = ['open', 'in_review', 'fulfilled', 'rejected', 'cancelled'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $openRequests = $this->dataSubjectRequests('active', 20);
        $retentionEvaluations = $this->retentionEvaluations(10);
        $summary = $this->summary();

        return [
            'privacy_governance_version' => 'privacy_consent_data_governance_v1_step25',
            'generated_at' => gmdate('c'),
            'summary' => $summary,
            'privacy_notices' => $this->privacyNotices('all'),
            'processing_records' => $this->processingRecords(),
            'retention_rules' => $this->retentionRules(),
            'latest_retention_evaluations' => $retentionEvaluations,
            'open_data_subject_requests' => $openRequests,
            'recent_consent_records' => $this->consentRecords('all', 15),
            'recent_data_exports' => $this->dataExports(10),
            'operator_actions' => $this->operatorActions($summary, $openRequests),
            'important_notes' => [
                'This is local/pilot governance, not a final GDPR legal approval.',
                'External AI providers, payment providers and email/SMS transports still need formal privacy review before production.',
                'Retention evaluation is dry-run only: Step 25 does not delete repair data automatically.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function privacyNotices(string $status = 'all'): array
    {
        if (in_array($status, ['draft', 'active', 'archived'], true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_privacy_notices WHERE status = :status ORDER BY scope ASC, title ASC');
            $stmt->execute(['status' => $status]);
            return array_map([$this, 'normalizePrivacyNotice'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->query("SELECT * FROM platform_privacy_notices ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END, scope ASC, title ASC");
        return array_map([$this, 'normalizePrivacyNotice'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function consentRecords(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if (in_array($status, $this->consentStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT c.*, n.code AS notice_code, n.title AS notice_title, n.version AS notice_version FROM platform_consent_records c JOIN platform_privacy_notices n ON n.id = c.notice_id WHERE c.status = :status ORDER BY c.created_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeConsentRecord'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT c.*, n.code AS notice_code, n.title AS notice_title, n.version AS notice_version FROM platform_consent_records c JOIN platform_privacy_notices n ON n.id = c.notice_id ORDER BY c.created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeConsentRecord'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function recordConsent(array $body, ?string $createdBy): array
    {
        $noticeId = trim((string) ($body['notice_id'] ?? ''));
        if ($noticeId === '') {
            $noticeCode = trim((string) ($body['notice_code'] ?? 'REPAIR-INTAKE-PRIVACY'));
            $notice = $this->privacyNoticeByCode($noticeCode);
            $noticeId = $notice['id'];
        } else {
            $this->requirePrivacyNotice($noticeId);
        }

        $subjectEmail = strtolower(trim((string) ($body['subject_email'] ?? '')));
        $userId = trim((string) ($body['user_id'] ?? '')) ?: null;
        if ($subjectEmail === '' && $userId === null) {
            throw new ValidationException(['subject_email' => ['subject_email or user_id is required.']]);
        }
        if ($subjectEmail !== '' && !filter_var($subjectEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['subject_email' => ['subject_email must be a valid email address.']]);
        }

        $consentType = strtolower(trim((string) ($body['consent_type'] ?? 'privacy_notice_acknowledged')));
        if ($consentType === '') {
            throw new ValidationException(['consent_type' => ['consent_type is required.']]);
        }

        $status = strtolower(trim((string) ($body['status'] ?? 'granted')));
        if (!in_array($status, ['granted', 'withdrawn'], true)) {
            throw new ValidationException(['status' => ['status must be granted or withdrawn.']]);
        }

        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_consent_records (id, user_id, subject_email, notice_id, consent_type, status, source, metadata_json, granted_at, withdrawn_at, created_by, created_at, updated_at) VALUES (:id, :user_id, :subject_email, :notice_id, :consent_type, :status, :source, :metadata_json, :granted_at, :withdrawn_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'subject_email' => $subjectEmail ?: null,
            'notice_id' => $noticeId,
            'consent_type' => $consentType,
            'status' => $status,
            'source' => trim((string) ($body['source'] ?? 'admin_console')) ?: 'admin_console',
            'metadata_json' => json_encode($body['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'granted_at' => $status === 'granted' ? $now : null,
            'withdrawn_at' => $status === 'withdrawn' ? $now : null,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->requireConsentRecord($id);
    }

    /** @return array<string, mixed> */
    public function withdrawConsent(string $id, ?string $userId, string $note = 'Consent withdrawn by operator.'): array
    {
        $record = $this->requireConsentRecord($id);
        $metadata = $record['metadata'];
        $metadata['withdrawal_note'] = $note;
        $metadata['withdrawn_by'] = $userId;
        $metadata['withdrawn_from'] = 'step25_privacy_console';
        $now = gmdate('c');

        $stmt = $this->pdo->prepare("UPDATE platform_consent_records SET status = 'withdrawn', withdrawn_at = :withdrawn_at, metadata_json = :metadata_json, updated_at = :updated_at WHERE id = :id");
        $stmt->execute([
            'withdrawn_at' => $now,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'id' => $id,
        ]);

        return $this->requireConsentRecord($id);
    }

    /** @return list<array<string, mixed>> */
    public function processingRecords(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM platform_data_processing_records ORDER BY CASE risk_level WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, domain ASC, name ASC");
        return array_map([$this, 'normalizeProcessingRecord'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function retentionRules(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM platform_retention_rules ORDER BY enabled DESC, scope ASC, retention_days ASC');
        return array_map([$this, 'normalizeRetentionRule'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function evaluateRetention(?string $userId): array
    {
        $rules = array_values(array_filter($this->retentionRules(), static fn (array $rule): bool => (bool) $rule['enabled']));
        $evaluations = [];
        foreach ($rules as $rule) {
            $evaluations[] = $this->evaluateRetentionRule($rule, $userId);
        }

        return [
            'evaluated_at' => gmdate('c'),
            'evaluated_count' => count($evaluations),
            'rules' => $evaluations,
            'summary' => $this->retentionSummary(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function retentionEvaluations(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT e.*, r.code AS rule_code, r.scope, r.table_name, r.retention_days, r.action FROM platform_retention_evaluations e JOIN platform_retention_rules r ON r.id = e.rule_id ORDER BY e.evaluated_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeRetentionEvaluation'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function dataSubjectRequests(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("SELECT * FROM platform_data_subject_requests WHERE status IN ('open', 'in_review') ORDER BY response_due_at ASC, created_at DESC LIMIT :limit");
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeDataSubjectRequest'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if (in_array($status, $this->dsrStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_data_subject_requests WHERE status = :status ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeDataSubjectRequest'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT * FROM platform_data_subject_requests ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeDataSubjectRequest'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createDataSubjectRequest(array $body, ?string $createdBy): array
    {
        $requestType = strtolower(trim((string) ($body['request_type'] ?? 'access')));
        if (!in_array($requestType, $this->dsrTypes, true)) {
            throw new ValidationException(['request_type' => ['request_type must be access, erasure, rectification, restriction, portability or objection.']]);
        }

        $subjectEmail = strtolower(trim((string) ($body['subject_email'] ?? '')));
        if ($subjectEmail === '' || !filter_var($subjectEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['subject_email' => ['subject_email must be a valid email address.']]);
        }

        $priority = strtolower(trim((string) ($body['priority'] ?? 'normal')));
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $now = gmdate('c');
        $responseDays = $priority === 'urgent' ? 7 : 30;
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_data_subject_requests (id, request_type, subject_email, subject_user_id, status, priority, description, response_due_at, created_by, created_at, updated_at) VALUES (:id, :request_type, :subject_email, :subject_user_id, :status, :priority, :description, :response_due_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'request_type' => $requestType,
            'subject_email' => $subjectEmail,
            'subject_user_id' => trim((string) ($body['subject_user_id'] ?? '')) ?: null,
            'status' => 'open',
            'priority' => $priority,
            'description' => trim((string) ($body['description'] ?? '')) ?: null,
            'response_due_at' => gmdate('c', strtotime('+' . $responseDays . ' days')),
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->requireDataSubjectRequest($id);
    }

    /** @return array<string, mixed> */
    public function resolveDataSubjectRequest(string $id, array $body, ?string $userId): array
    {
        $this->requireDataSubjectRequest($id);
        $status = strtolower(trim((string) ($body['status'] ?? 'fulfilled')));
        if (!in_array($status, ['fulfilled', 'rejected', 'cancelled', 'in_review'], true)) {
            throw new ValidationException(['status' => ['status must be fulfilled, rejected, cancelled or in_review.']]);
        }
        $now = gmdate('c');
        $resolvedAt = in_array($status, ['fulfilled', 'rejected', 'cancelled'], true) ? $now : null;
        $notes = trim((string) ($body['resolution_notes'] ?? 'Step 25 data subject request updated by operator.'));

        $stmt = $this->pdo->prepare('UPDATE platform_data_subject_requests SET status = :status, resolved_at = :resolved_at, resolution_notes = :resolution_notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'resolved_at' => $resolvedAt,
            'resolution_notes' => $notes . ($userId ? ' Operator: ' . $userId : ''),
            'updated_at' => $now,
            'id' => $id,
        ]);

        return $this->requireDataSubjectRequest($id);
    }

    /** @return array<string, mixed> */
    public function generateDataExport(string $requestId, ?string $userId): array
    {
        $request = $this->requireDataSubjectRequest($requestId);
        $payload = $this->buildSubjectExport((string) $request['subject_email'], $request['subject_user_id'] ?? null);
        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_data_exports (id, request_id, subject_email, status, payload_json, generated_by, generated_at, expires_at, created_at) VALUES (:id, :request_id, :subject_email, :status, :payload_json, :generated_by, :generated_at, :expires_at, :created_at)');
        $stmt->execute([
            'id' => $id,
            'request_id' => $requestId,
            'subject_email' => $request['subject_email'],
            'status' => 'generated',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'generated_by' => $userId,
            'generated_at' => $now,
            'expires_at' => gmdate('c', strtotime('+7 days')),
            'created_at' => $now,
        ]);

        return $this->requireDataExport($id);
    }

    /** @return list<array<string, mixed>> */
    public function dataExports(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT e.*, r.request_type, r.status AS request_status FROM platform_data_exports e JOIN platform_data_subject_requests r ON r.id = e.request_id ORDER BY e.generated_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeDataExport'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function evaluateRetentionRule(array $rule, ?string $userId): array
    {
        $summary = [
            'dry_run' => true,
            'action' => $rule['action'],
            'note' => 'No data is deleted by Step 25. Operator review is required before any destructive retention action.',
        ];
        $candidateCount = 0;
        $oldestRecordAt = null;
        $status = 'ok';

        $tableName = (string) $rule['table_name'];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            $status = 'blocked';
            $summary['warning'] = 'Unsafe table name in retention rule.';
        } elseif (!$this->tableExists($tableName)) {
            $status = 'not_available';
            $summary['warning'] = 'Table is not available in this environment yet.';
        } else {
            $dateColumn = $this->retentionDateColumn($tableName);
            if ($dateColumn === null) {
                $status = 'not_available';
                $summary['warning'] = 'Table does not expose a known retention timestamp; manual retention review required.';
            } else {
                $cutoff = gmdate('c', strtotime('-' . (int) $rule['retention_days'] . ' days'));
                $stmt = $this->pdo->prepare("SELECT COUNT(*) AS count, MIN({$dateColumn}) AS oldest FROM {$tableName} WHERE {$dateColumn} < :cutoff");
                $stmt->execute(['cutoff' => $cutoff]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0, 'oldest' => null];
                $candidateCount = (int) $row['count'];
                $oldestRecordAt = $row['oldest'] ?: null;
                $summary['cutoff_at'] = $cutoff;
                $summary['date_column'] = $dateColumn;
                $summary['candidate_count'] = $candidateCount;
                $status = $candidateCount > 0 ? 'review_required' : 'ok';
            }
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_retention_evaluations (id, rule_id, candidate_count, oldest_record_at, status, summary_json, evaluated_by, evaluated_at, created_at) VALUES (:id, :rule_id, :candidate_count, :oldest_record_at, :status, :summary_json, :evaluated_by, :evaluated_at, :created_at)');
        $stmt->execute([
            'id' => $id,
            'rule_id' => $rule['id'],
            'candidate_count' => $candidateCount,
            'oldest_record_at' => $oldestRecordAt,
            'status' => $status,
            'summary_json' => json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'evaluated_by' => $userId,
            'evaluated_at' => $now,
            'created_at' => $now,
        ]);

        return $this->requireRetentionEvaluation($id);
    }

    /** @return array<string, mixed> */
    private function buildSubjectExport(string $email, ?string $subjectUserId): array
    {
        $payload = [
            'export_version' => 'step25_subject_access_draft',
            'generated_at' => gmdate('c'),
            'subject_email' => $email,
            'subject_user_id' => $subjectUserId,
            'scope_note' => 'Local pilot export. Binary uploaded files are not embedded; attachment metadata is listed only.',
            'user' => null,
            'repair_cases' => [],
            'repair_attachments' => [],
            'consent_records' => [],
            'data_subject_requests' => [],
        ];

        $userId = $subjectUserId;
        $stmt = $this->pdo->prepare('SELECT id, name, email, role, created_at FROM users WHERE email = :email OR id = :id LIMIT 1');
        $stmt->execute(['email' => $email, 'id' => $subjectUserId ?? '']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($user) {
            $payload['user'] = $user;
            $userId = (string) $user['id'];
        }

        if ($userId) {
            $stmt = $this->pdo->prepare('SELECT id, title, category, status, owner_id, created_at, updated_at FROM repair_cases WHERE owner_id = :owner_id ORDER BY created_at DESC LIMIT 100');
            $stmt->execute(['owner_id' => $userId]);
            $payload['repair_cases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare('SELECT a.id, a.repair_case_id, a.kind, a.original_filename, a.mime_type, a.size_bytes, a.created_at FROM repair_attachments a JOIN repair_cases c ON c.id = a.repair_case_id WHERE c.owner_id = :owner_id ORDER BY a.created_at DESC LIMIT 100');
            $stmt->execute(['owner_id' => $userId]);
            $payload['repair_attachments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->prepare('SELECT c.id, c.subject_email, c.user_id, c.consent_type, c.status, c.source, c.granted_at, c.withdrawn_at, c.created_at, n.code AS notice_code, n.title AS notice_title, n.version AS notice_version FROM platform_consent_records c JOIN platform_privacy_notices n ON n.id = c.notice_id WHERE c.subject_email = :email OR c.user_id = :user_id ORDER BY c.created_at DESC LIMIT 100');
        $stmt->execute(['email' => $email, 'user_id' => $userId ?? '']);
        $payload['consent_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare('SELECT id, request_type, subject_email, subject_user_id, status, priority, description, response_due_at, resolved_at, resolution_notes, created_at FROM platform_data_subject_requests WHERE subject_email = :email OR subject_user_id = :user_id ORDER BY created_at DESC LIMIT 100');
        $stmt->execute(['email' => $email, 'user_id' => $userId ?? '']);
        $payload['data_subject_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        return [
            'privacy_notices' => $this->countTable('platform_privacy_notices'),
            'draft_notices' => $this->countWhere('platform_privacy_notices', "status = 'draft'"),
            'processing_records' => $this->countTable('platform_data_processing_records'),
            'high_risk_processing_records' => $this->countWhere('platform_data_processing_records', "risk_level = 'high'"),
            'active_consents' => $this->countWhere('platform_consent_records', "status = 'granted'"),
            'withdrawn_consents' => $this->countWhere('platform_consent_records', "status = 'withdrawn'"),
            'open_data_subject_requests' => $this->countWhere('platform_data_subject_requests', "status IN ('open', 'in_review')"),
            'retention_rules_enabled' => $this->countWhere('platform_retention_rules', 'enabled = 1'),
            'retention_reviews_required' => $this->countWhere('platform_retention_evaluations', "status = 'review_required'"),
            'data_exports_generated' => $this->countTable('platform_data_exports'),
        ];
    }

    /** @return array<string, mixed> */
    private function retentionSummary(): array
    {
        return [
            'rules_enabled' => $this->countWhere('platform_retention_rules', 'enabled = 1'),
            'latest_evaluations' => $this->countTable('platform_retention_evaluations'),
            'review_required' => $this->countWhere('platform_retention_evaluations', "status = 'review_required'"),
        ];
    }

    /** @param list<array<string, mixed>> $openRequests @return list<string> */
    private function operatorActions(array $summary, array $openRequests): array
    {
        $actions = [];
        if (($summary['draft_notices'] ?? 0) > 0) {
            $actions[] = 'Review and approve draft privacy notices before any real beta onboarding.';
        }
        if (($summary['high_risk_processing_records'] ?? 0) > 0) {
            $actions[] = 'Perform a human privacy review on high-risk processing records, especially repair photos and AI learning.';
        }
        if ($openRequests !== []) {
            $actions[] = 'Resolve open data subject requests before pilot launch decisions.';
        }
        if (($summary['retention_reviews_required'] ?? 0) > 0) {
            $actions[] = 'Retention evaluation found records requiring review; no automatic deletion has been executed.';
        }
        if ($actions === []) {
            $actions[] = 'Privacy governance has no immediate blocking action in local/pilot mode.';
        }
        return $actions;
    }

    private function countTable(string $table): int
    {
        return $this->tableExists($table) ? (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() : 0;
    }

    private function countWhere(string $table, string $where): int
    {
        return $this->tableExists($table) ? (int) $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn() : 0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);
        return (bool) $stmt->fetchColumn();
    }


    private function retentionDateColumn(string $table): ?string
    {
        foreach (['created_at', 'occurred_at', 'dispatched_at', 'generated_at', 'evaluated_at'] as $candidate) {
            if ($this->tableHasColumn($table, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->query('PRAGMA table_info(' . $table . ')');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, mixed> */
    private function requirePrivacyNotice(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_privacy_notices WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Privacy notice not found.');
        }
        return $this->normalizePrivacyNotice($row);
    }

    /** @return array<string, mixed> */
    private function privacyNoticeByCode(string $code): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_privacy_notices WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Privacy notice not found.');
        }
        return $this->normalizePrivacyNotice($row);
    }

    /** @return array<string, mixed> */
    private function requireConsentRecord(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, n.code AS notice_code, n.title AS notice_title, n.version AS notice_version FROM platform_consent_records c JOIN platform_privacy_notices n ON n.id = c.notice_id WHERE c.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Consent record not found.');
        }
        return $this->normalizeConsentRecord($row);
    }

    /** @return array<string, mixed> */
    private function requireRetentionEvaluation(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT e.*, r.code AS rule_code, r.scope, r.table_name, r.retention_days, r.action FROM platform_retention_evaluations e JOIN platform_retention_rules r ON r.id = e.rule_id WHERE e.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Retention evaluation not found.');
        }
        return $this->normalizeRetentionEvaluation($row);
    }

    /** @return array<string, mixed> */
    private function requireDataSubjectRequest(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_data_subject_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Data subject request not found.');
        }
        return $this->normalizeDataSubjectRequest($row);
    }

    /** @return array<string, mixed> */
    private function requireDataExport(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT e.*, r.request_type, r.status AS request_status FROM platform_data_exports e JOIN platform_data_subject_requests r ON r.id = e.request_id WHERE e.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Data export not found.');
        }
        return $this->normalizeDataExport($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePrivacyNotice(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'code' => (string) $row['code'],
            'title' => (string) $row['title'],
            'scope' => (string) $row['scope'],
            'version' => (string) $row['version'],
            'status' => (string) $row['status'],
            'effective_from' => $row['effective_from'] ?? null,
            'review_due_at' => $row['review_due_at'] ?? null,
            'summary' => $row['summary'] ?? null,
            'policy_text' => $row['policy_text'] ?? null,
            'rights' => json_decode((string) ($row['rights_json'] ?? '[]'), true) ?: [],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeConsentRecord(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'user_id' => $row['user_id'] ?? null,
            'subject_email' => $row['subject_email'] ?? null,
            'notice_id' => (string) $row['notice_id'],
            'notice_code' => $row['notice_code'] ?? null,
            'notice_title' => $row['notice_title'] ?? null,
            'notice_version' => $row['notice_version'] ?? null,
            'consent_type' => (string) $row['consent_type'],
            'status' => (string) $row['status'],
            'source' => (string) $row['source'],
            'metadata' => json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [],
            'granted_at' => $row['granted_at'] ?? null,
            'withdrawn_at' => $row['withdrawn_at'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeProcessingRecord(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'activity_code' => (string) $row['activity_code'],
            'name' => (string) $row['name'],
            'domain' => (string) $row['domain'],
            'data_categories' => json_decode((string) ($row['data_categories_json'] ?? '[]'), true) ?: [],
            'purpose' => (string) $row['purpose'],
            'lawful_basis' => (string) $row['lawful_basis'],
            'retention_days' => (int) $row['retention_days'],
            'processors' => json_decode((string) ($row['processors_json'] ?? '[]'), true) ?: [],
            'risk_level' => (string) $row['risk_level'],
            'status' => (string) $row['status'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRetentionRule(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'code' => (string) $row['code'],
            'scope' => (string) $row['scope'],
            'table_name' => (string) $row['table_name'],
            'data_category' => (string) $row['data_category'],
            'retention_days' => (int) $row['retention_days'],
            'action' => (string) $row['action'],
            'enabled' => ((int) $row['enabled']) === 1,
            'description' => $row['description'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRetentionEvaluation(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'rule_id' => (string) $row['rule_id'],
            'rule_code' => $row['rule_code'] ?? null,
            'scope' => $row['scope'] ?? null,
            'table_name' => $row['table_name'] ?? null,
            'retention_days' => isset($row['retention_days']) ? (int) $row['retention_days'] : null,
            'action' => $row['action'] ?? null,
            'candidate_count' => (int) $row['candidate_count'],
            'oldest_record_at' => $row['oldest_record_at'] ?? null,
            'status' => (string) $row['status'],
            'summary' => json_decode((string) ($row['summary_json'] ?? '{}'), true) ?: [],
            'evaluated_by' => $row['evaluated_by'] ?? null,
            'evaluated_at' => (string) $row['evaluated_at'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeDataSubjectRequest(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'request_type' => (string) $row['request_type'],
            'subject_email' => (string) $row['subject_email'],
            'subject_user_id' => $row['subject_user_id'] ?? null,
            'status' => (string) $row['status'],
            'priority' => (string) $row['priority'],
            'description' => $row['description'] ?? null,
            'response_due_at' => (string) $row['response_due_at'],
            'resolved_at' => $row['resolved_at'] ?? null,
            'resolution_notes' => $row['resolution_notes'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeDataExport(array $row): array
    {
        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true) ?: [];
        return [
            'id' => (string) $row['id'],
            'request_id' => (string) $row['request_id'],
            'request_type' => $row['request_type'] ?? null,
            'request_status' => $row['request_status'] ?? null,
            'subject_email' => (string) $row['subject_email'],
            'status' => (string) $row['status'],
            'payload' => $payload,
            'payload_summary' => [
                'user_found' => isset($payload['user']) && $payload['user'] !== null,
                'repair_cases' => is_countable($payload['repair_cases'] ?? null) ? count($payload['repair_cases']) : 0,
                'repair_attachments' => is_countable($payload['repair_attachments'] ?? null) ? count($payload['repair_attachments']) : 0,
                'consent_records' => is_countable($payload['consent_records'] ?? null) ? count($payload['consent_records']) : 0,
            ],
            'generated_by' => $row['generated_by'] ?? null,
            'generated_at' => (string) $row['generated_at'],
            'expires_at' => (string) $row['expires_at'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
