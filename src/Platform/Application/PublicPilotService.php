<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class PublicPilotService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'summary' => [
                'active_public_pages' => $this->count('platform_public_pilot_demo_pages', "status = 'active'"),
                'new_intake_submissions' => $this->count('platform_external_pilot_intake_submissions', "status = 'new'"),
                'triaged_intake_submissions' => $this->count('platform_external_pilot_intake_submissions', "status IN ('triaged','shortlisted','accepted')"),
                'provider_maker_leads' => $this->count('platform_external_pilot_intake_submissions', "stakeholder_type IN ('provider','maker','partner')"),
                'real_world_validation_cases' => $this->count('platform_real_world_validation_cases'),
                'active_validation_cases' => $this->count('platform_real_world_validation_cases', "status IN ('candidate','reviewing','approved','in_pilot')"),
                'high_fit_leads' => $this->count('platform_pilot_stakeholder_lead_scores', "score >= 80"),
                'average_fit_score' => $this->averageLeadScore(),
            ],
            'public_demo' => $this->publicDemo(),
            'intake_submissions' => $this->intakeSubmissions('all', 'all', 10),
            'validation_cases' => $this->validationCases('all', 10),
            'lead_scores' => $this->leadScores('', 10),
            'audit_log' => $this->auditLog(10),
            'scope_note' => 'Step 42 exposes a controlled public-pilot surface and admin triage workflow. It collects interest and real-world validation evidence but does not approve real payments, fulfilment, warranty, provider activation or public claims.',
        ];
    }

    /** @return array<string, mixed> */
    public function publicDemo(): array
    {
        return [
            'positioning' => 'Re-born is a Repair Intelligence Platform. The pilot invites providers, makers, partners and early repair users into a controlled validation loop.',
            'mission' => 'Allow anyone to repair anything.',
            'pages' => $this->demoPages('active', 20),
            'stakeholder_paths' => [
                ['type' => 'repair_user', 'label' => 'Submit a real repair case', 'goal' => 'Validate that the journey can move from broken object to governed repair outcome.'],
                ['type' => 'provider', 'label' => 'Join the provider pilot', 'goal' => 'Validate local/distributed fulfilment, quote and proof-of-repair governance.'],
                ['type' => 'maker', 'label' => 'Contribute repair modelling capability', 'goal' => 'Validate maker-economy, licensing and model-quality governance.'],
                ['type' => 'partner', 'label' => 'Explore enterprise/partner pilot', 'goal' => 'Validate stakeholder needs, integrations and pilot launch constraints.'],
            ],
            'non_promises' => [
                'No automatic acceptance into the beta.',
                'No promise that a submitted object will be repaired.',
                'No real payment, payout, logistics or warranty workflow is enabled by intake alone.',
                'AI, CAD and sustainability outputs remain pilot evidence until separately reviewed.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function demoPages(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_public_pilot_demo_pages';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY audience ASC, title ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn (array $row): array => $this->normalizeDemoPage($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createIntakeSubmission(array $body, ?string $userId = null): array
    {
        $stakeholderType = trim((string) ($body['stakeholder_type'] ?? 'partner')) ?: 'partner';
        if (!in_array($stakeholderType, ['repair_user', 'provider', 'maker', 'partner', 'enterprise'], true)) {
            throw new ValidationException(['stakeholder_type' => ['Stakeholder type must be repair_user, provider, maker, partner or enterprise.']]);
        }

        $contactName = trim((string) ($body['contact_name'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $motivation = trim((string) ($body['motivation'] ?? ''));
        if ($contactName === '') {
            throw new ValidationException(['contact_name' => ['contact_name is required.']]);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['email' => ['A valid email is required.']]);
        }
        if ($motivation === '') {
            throw new ValidationException(['motivation' => ['motivation is required.']]);
        }

        $capabilities = $this->listValue($body['capabilities'] ?? []);
        $categories = $this->listValue($body['repair_categories'] ?? []);
        $score = $this->scoreSubmission($stakeholderType, $capabilities, $categories, $motivation, (string) ($body['city'] ?? ''), (string) ($body['country'] ?? ''));
        $risk = $this->riskLevel($score, $body);
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'PILOT-INTAKE-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));

        $stmt = $this->pdo->prepare('INSERT INTO platform_external_pilot_intake_submissions (id, submission_code, stakeholder_type, status, organization_name, contact_name, email, country, city, capabilities_json, repair_categories_json, motivation, pilot_fit_score, risk_level, triage_notes, source, created_at, updated_at, reviewed_by, reviewed_at) VALUES (:id, :submission_code, :stakeholder_type, :status, :organization_name, :contact_name, :email, :country, :city, :capabilities_json, :repair_categories_json, :motivation, :pilot_fit_score, :risk_level, :triage_notes, :source, :created_at, :updated_at, :reviewed_by, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'submission_code' => $code,
            'stakeholder_type' => $stakeholderType,
            'status' => 'new',
            'organization_name' => trim((string) ($body['organization_name'] ?? '')),
            'contact_name' => $contactName,
            'email' => $email,
            'country' => trim((string) ($body['country'] ?? '')),
            'city' => trim((string) ($body['city'] ?? '')),
            'capabilities_json' => $this->encode($capabilities),
            'repair_categories_json' => $this->encode($categories),
            'motivation' => $motivation,
            'pilot_fit_score' => $score,
            'risk_level' => $risk,
            'triage_notes' => trim((string) ($body['triage_notes'] ?? '')),
            'source' => trim((string) ($body['source'] ?? 'public_pilot_demo')) ?: 'public_pilot_demo',
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $this->createLeadScore($id, $score, $stakeholderType, $risk, $capabilities, $categories, 'Initial public pilot intake score.');
        $this->audit('public_pilot_intake_created', 'intake_submission', $id, sprintf('Public pilot intake %s submitted by %s.', $code, $contactName), ['stakeholder_type' => $stakeholderType, 'score' => $score], $userId);
        return $this->requireSubmission($id);
    }

    /** @return list<array<string, mixed>> */
    public function intakeSubmissions(string $status = 'all', string $stakeholderType = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $where = [];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($stakeholderType !== 'all') {
            $where[] = 'stakeholder_type = :stakeholder_type';
            $params['stakeholder_type'] = $stakeholderType;
        }
        $sql = 'SELECT * FROM platform_external_pilot_intake_submissions';
        if ($where !== []) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY pilot_fit_score DESC, created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn (array $row): array => $this->normalizeSubmission($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function reviewIntakeSubmission(string $id, array $body, ?string $userId): array
    {
        $submission = $this->requireSubmission($id);
        $status = trim((string) ($body['status'] ?? 'triaged')) ?: 'triaged';
        if (!in_array($status, ['new', 'triaged', 'shortlisted', 'accepted', 'rejected', 'needs_info'], true)) {
            throw new ValidationException(['status' => ['Status must be new, triaged, shortlisted, accepted, rejected or needs_info.']]);
        }
        $notes = trim((string) ($body['triage_notes'] ?? $submission['triage_notes'] ?? ''));
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_external_pilot_intake_submissions SET status = :status, triage_notes = :triage_notes, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'triage_notes' => $notes,
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
        $this->audit('pilot_intake_reviewed', 'intake_submission', $id, sprintf('Pilot intake %s reviewed as %s.', $submission['submission_code'], $status), ['previous_status' => $submission['status']], $userId);
        return $this->requireSubmission($id);
    }

    /** @return array<string, mixed> */
    public function createValidationCaseFromSubmission(string $submissionId, array $body, ?string $userId): array
    {
        $submission = $this->requireSubmission($submissionId);
        $payload = $body + [
            'submission_id' => $submissionId,
            'repair_category' => ((array) ($submission['repair_categories'] ?? []))[0] ?? 'general',
            'object_name' => $submission['stakeholder_type'] === 'repair_user' ? 'Submitted repair object' : 'Pilot capability validation case',
            'problem_statement' => $submission['motivation'] ?? 'Validate stakeholder fit with a real-world repair scenario.',
            'pilot_fit_score' => $submission['pilot_fit_score'] ?? 0,
            'governance_risk' => $submission['risk_level'] ?? 'medium',
        ];
        return $this->createValidationCase($payload, $userId);
    }

    /** @return list<array<string, mixed>> */
    public function validationCases(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT c.*, s.submission_code, s.stakeholder_type, s.organization_name FROM platform_real_world_validation_cases c LEFT JOIN platform_external_pilot_intake_submissions s ON s.id = c.submission_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE c.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY c.pilot_fit_score DESC, c.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn (array $row): array => $this->normalizeValidationCase($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createValidationCase(array $body, ?string $userId): array
    {
        $objectName = trim((string) ($body['object_name'] ?? ''));
        $problem = trim((string) ($body['problem_statement'] ?? ''));
        if ($objectName === '') {
            throw new ValidationException(['object_name' => ['object_name is required.']]);
        }
        if ($problem === '') {
            throw new ValidationException(['problem_statement' => ['problem_statement is required.']]);
        }
        $submissionId = trim((string) ($body['submission_id'] ?? '')) ?: null;
        if ($submissionId !== null) { $this->requireSubmission($submissionId); }
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'REAL-CASE-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $score = max(0, min(100, (int) ($body['pilot_fit_score'] ?? 55)));
        $risk = trim((string) ($body['governance_risk'] ?? 'medium')) ?: 'medium';
        if (!in_array($risk, ['low', 'medium', 'high'], true)) { $risk = 'medium'; }

        $stmt = $this->pdo->prepare('INSERT INTO platform_real_world_validation_cases (id, case_code, submission_id, status, repair_category, object_name, problem_statement, success_criteria_json, evidence_json, pilot_fit_score, governance_risk, owner, created_at, updated_at, reviewed_at) VALUES (:id, :case_code, :submission_id, :status, :repair_category, :object_name, :problem_statement, :success_criteria_json, :evidence_json, :pilot_fit_score, :governance_risk, :owner, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'case_code' => $code,
            'submission_id' => $submissionId,
            'status' => trim((string) ($body['status'] ?? 'candidate')) ?: 'candidate',
            'repair_category' => trim((string) ($body['repair_category'] ?? 'general')) ?: 'general',
            'object_name' => $objectName,
            'problem_statement' => $problem,
            'success_criteria_json' => $this->encode($body['success_criteria'] ?? ['Functional restoration can be verified.', 'Governance caveats are accepted.']),
            'evidence_json' => $this->encode($body['evidence'] ?? []),
            'pilot_fit_score' => $score,
            'governance_risk' => $risk,
            'owner' => trim((string) ($body['owner'] ?? 'operations')) ?: 'operations',
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        $this->audit('real_world_validation_case_created', 'validation_case', $id, sprintf('Real-world validation case %s created.', $code), ['submission_id' => $submissionId, 'score' => $score], $userId);
        return $this->requireValidationCase($id);
    }

    /** @return array<string, mixed> */
    public function updateValidationCaseStatus(string $id, array $body, ?string $userId): array
    {
        $case = $this->requireValidationCase($id);
        $status = trim((string) ($body['status'] ?? 'reviewing')) ?: 'reviewing';
        if (!in_array($status, ['candidate', 'reviewing', 'approved', 'in_pilot', 'validated', 'rejected', 'blocked'], true)) {
            throw new ValidationException(['status' => ['Status must be candidate, reviewing, approved, in_pilot, validated, rejected or blocked.']]);
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_real_world_validation_cases SET status = :status, updated_at = :updated_at, reviewed_at = :reviewed_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'updated_at' => $now,
            'reviewed_at' => in_array($status, ['approved', 'validated', 'rejected', 'blocked'], true) ? $now : ($case['reviewed_at'] ?? null),
            'id' => $id,
        ]);
        $this->audit('validation_case_status_updated', 'validation_case', $id, sprintf('Validation case %s moved to %s.', $case['case_code'], $status), ['previous_status' => $case['status']], $userId);
        return $this->requireValidationCase($id);
    }

    /** @return list<array<string, mixed>> */
    public function leadScores(string $submissionId = '', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT ls.*, s.submission_code, s.stakeholder_type, s.organization_name FROM platform_pilot_stakeholder_lead_scores ls JOIN platform_external_pilot_intake_submissions s ON s.id = ls.submission_id';
        $params = [];
        if ($submissionId !== '') {
            $sql .= ' WHERE ls.submission_id = :submission_id';
            $params['submission_id'] = $submissionId;
        }
        $sql .= ' ORDER BY ls.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['score'] = (int) ($row['score'] ?? 0);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function evaluatePublicPilot(): array
    {
        $activePages = $this->count('platform_public_pilot_demo_pages', "status = 'active'");
        $submissions = $this->count('platform_external_pilot_intake_submissions');
        $triaged = $this->count('platform_external_pilot_intake_submissions', "status IN ('triaged','shortlisted','accepted')");
        $validationCases = $this->count('platform_real_world_validation_cases');
        $highFit = $this->count('platform_pilot_stakeholder_lead_scores', "score >= 80");
        $blockedCases = $this->count('platform_real_world_validation_cases', "status = 'blocked'");
        $score = (int) round(min(20, $activePages * 7) + min(25, $submissions * 5) + min(25, $triaged * 8) + min(20, $validationCases * 10) + min(10, $highFit * 5) - min(20, $blockedCases * 10));
        $score = max(0, min(100, $score));
        $recommendation = $score >= 82 ? 'ready_for_controlled_public_pilot' : ($score >= 62 ? 'continue_private_recruiting' : 'hold_and_collect_more_evidence');
        return [
            'score' => $score,
            'recommendation' => $recommendation,
            'conditions' => [
                'Every public surface must keep pilot caveats visible.',
                'Provider/maker leads require governance review before activation.',
                'Real-world validation cases require safe evidence, customer acceptance and proof-of-repair controls.',
                'Legal, privacy, payments, fulfilment, warranty and public claims remain gated by earlier readiness layers.',
            ],
            'metrics' => [
                'active_public_pages' => $activePages,
                'intake_submissions' => $submissions,
                'triaged_submissions' => $triaged,
                'validation_cases' => $validationCases,
                'high_fit_leads' => $highFit,
                'blocked_cases' => $blockedCases,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_public_pilot_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decode($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function requireSubmission(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_external_pilot_intake_submissions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Pilot intake submission was not found.']]); }
        return $this->normalizeSubmission($row);
    }

    /** @return array<string, mixed> */
    private function requireValidationCase(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, s.submission_code, s.stakeholder_type, s.organization_name FROM platform_real_world_validation_cases c LEFT JOIN platform_external_pilot_intake_submissions s ON s.id = c.submission_id WHERE c.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Real-world validation case was not found.']]); }
        return $this->normalizeValidationCase($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeDemoPage(array $row): array
    {
        $row['requirements'] = $this->decode($row['requirements_json'] ?? '[]');
        $row['caveats'] = $this->decode($row['caveats_json'] ?? '[]');
        $row['metadata'] = $this->decode($row['metadata_json'] ?? '{}');
        unset($row['requirements_json'], $row['caveats_json'], $row['metadata_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeSubmission(array $row): array
    {
        $row['pilot_fit_score'] = (int) ($row['pilot_fit_score'] ?? 0);
        $row['capabilities'] = $this->decode($row['capabilities_json'] ?? '[]');
        $row['repair_categories'] = $this->decode($row['repair_categories_json'] ?? '[]');
        unset($row['capabilities_json'], $row['repair_categories_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeValidationCase(array $row): array
    {
        $row['pilot_fit_score'] = (int) ($row['pilot_fit_score'] ?? 0);
        $row['success_criteria'] = $this->decode($row['success_criteria_json'] ?? '[]');
        $row['evidence'] = $this->decode($row['evidence_json'] ?? '[]');
        unset($row['success_criteria_json'], $row['evidence_json']);
        return $row;
    }

    /** @param list<string> $capabilities @param list<string> $categories */
    private function createLeadScore(string $submissionId, int $score, string $stakeholderType, string $risk, array $capabilities, array $categories, string $notes): void
    {
        $band = $score >= 80 ? 'high' : ($score >= 62 ? 'medium' : 'low');
        $readiness = $score >= 80 ? 'ready_for_review' : ($score >= 62 ? 'needs_review' : 'low_signal');
        $strategicFit = in_array($stakeholderType, ['provider', 'maker'], true) ? 'supply_side_signal' : ($stakeholderType === 'repair_user' ? 'real_case_signal' : 'partner_signal');
        if (in_array('local_pickup', $capabilities, true) || in_array('small_appliances', $categories, true)) {
            $strategicFit = 'local_real_world_validation';
        }
        $stmt = $this->pdo->prepare('INSERT INTO platform_pilot_stakeholder_lead_scores (id, submission_id, score, score_band, readiness_signal, strategic_fit_signal, risk_signal, notes, created_at) VALUES (:id, :submission_id, :score, :score_band, :readiness_signal, :strategic_fit_signal, :risk_signal, :notes, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'submission_id' => $submissionId,
            'score' => $score,
            'score_band' => $band,
            'readiness_signal' => $readiness,
            'strategic_fit_signal' => $strategicFit,
            'risk_signal' => $risk,
            'notes' => $notes,
            'created_at' => gmdate('c'),
        ]);
    }

    /** @param mixed $value @return list<string> */
    private function listValue($value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') { $items[] = $item; }
        }
        return array_values(array_unique($items));
    }

    /** @param list<string> $capabilities @param list<string> $categories */
    private function scoreSubmission(string $stakeholderType, array $capabilities, array $categories, string $motivation, string $city, string $country): int
    {
        $score = 35;
        if (in_array($stakeholderType, ['provider', 'maker'], true)) { $score += 12; }
        if ($stakeholderType === 'repair_user') { $score += 10; }
        if (count($capabilities) >= 2) { $score += 12; }
        if (count($categories) >= 1) { $score += 10; }
        if (strlen($motivation) >= 80) { $score += 10; }
        if (trim($city) !== '') { $score += 6; }
        if (strtolower(trim($country)) === 'italy' || strtolower(trim($country)) === 'italia') { $score += 5; }
        foreach (['fdm_printing', 'cad_modeling', 'reverse_engineering', 'local_pickup', 'proof_of_repair'] as $signal) {
            if (in_array($signal, $capabilities, true)) { $score += 3; }
        }
        return max(0, min(100, $score));
    }

    /** @param array<string, mixed> $body */
    private function riskLevel(int $score, array $body): string
    {
        $declared = trim((string) ($body['risk_level'] ?? ''));
        if (in_array($declared, ['low', 'medium', 'high'], true)) { return $declared; }
        if ($score >= 85) { return 'low'; }
        if ($score < 55) { return 'high'; }
        return 'medium';
    }

    private function averageLeadScore(): int
    {
        $value = $this->pdo->query('SELECT AVG(score) FROM platform_pilot_stakeholder_lead_scores')->fetchColumn();
        return $value !== false && $value !== null ? (int) round((float) $value) : 0;
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_public_pilot_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
            'metadata_json' => $this->encode($metadata),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }

    private function count(string $table, string $where = '1 = 1'): int
    {
        $stmt = $this->pdo->query(sprintf('SELECT COUNT(*) FROM %s WHERE %s', $table, $where));
        return $stmt ? (int) $stmt->fetchColumn() : 0;
    }

    /** @param mixed $value */
    private function encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /** @return mixed */
    private function decode(string $json)
    {
        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
