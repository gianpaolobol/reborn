<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class DemoWalkthroughService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $latestSession = $this->latestSession();
        return [
            'summary' => [
                'active_modes' => $this->count('platform_demo_modes', "status = 'active'"),
                'active_steps' => $this->count('platform_guided_walkthrough_steps', "status = 'active'"),
                'demo_sessions' => $this->count('platform_demo_sessions'),
                'completed_sessions' => $this->count('platform_demo_sessions', "status = 'completed'"),
                'feedback_records' => $this->count('platform_demo_feedback'),
                'open_readiness_reviews' => $this->count('platform_demo_readiness_reviews', "status IN ('open','needs_work')"),
                'latest_session_status' => (string) ($latestSession['status'] ?? 'none'),
                'recommended_mode' => 'investor_walkthrough',
            ],
            'latest_session' => $latestSession,
            'modes' => $this->demoModes('active'),
            'walkthrough_steps' => $this->walkthroughSteps(null, 'active'),
            'readiness_reviews' => $this->readinessReviews('active', 8),
            'scope_note' => 'Step 40 creates guided local demo and investor walkthrough governance. It does not convert mock AI, payments, logistics, warranty or sustainability estimates into production-grade claims.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function demoModes(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_demo_modes';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY audience ASC, name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function walkthroughSteps(?string $modeId = null, string $status = 'active'): array
    {
        $params = [];
        $sql = 'SELECT s.*, m.mode_key, m.name AS mode_name, m.audience AS mode_audience FROM platform_guided_walkthrough_steps s JOIN platform_demo_modes m ON m.id = s.mode_id WHERE 1 = 1';
        if ($modeId !== null && $modeId !== '') {
            $sql .= ' AND s.mode_id = :mode_id';
            $params['mode_id'] = $modeId;
        }
        if ($status !== 'all') {
            $sql .= ' AND s.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY m.audience ASC, s.sort_order ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $row): array {
            $row['sort_order'] = (int) $row['sort_order'];
            $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '[]');
            unset($row['evidence_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createSession(array $body, ?string $userId): array
    {
        $modeId = trim((string) ($body['mode_id'] ?? '')) ?: $this->defaultModeId();
        $this->requireMode($modeId);
        $audience = trim((string) ($body['audience'] ?? 'investor')) ?: 'investor';
        $presenter = trim((string) ($body['presenter_name'] ?? '')) ?: null;
        $notes = trim((string) ($body['notes'] ?? ''));
        $status = trim((string) ($body['status'] ?? 'draft')) ?: 'draft';
        if (!in_array($status, ['draft', 'running'], true)) {
            throw new ValidationException(['status' => ['Status must be draft or running when creating a demo session.']]);
        }
        $firstStep = $this->firstStepForMode($modeId);
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'DEMO-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $startedAt = $status === 'running' ? $now : null;
        $stmt = $this->pdo->prepare('INSERT INTO platform_demo_sessions (id, session_code, mode_id, audience, status, presenter_name, current_step_key, notes, created_by, started_at, completed_at, created_at, updated_at) VALUES (:id, :session_code, :mode_id, :audience, :status, :presenter_name, :current_step_key, :notes, :created_by, :started_at, :completed_at, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'session_code' => $code,
            'mode_id' => $modeId,
            'audience' => $audience,
            'status' => $status,
            'presenter_name' => $presenter,
            'current_step_key' => $firstStep['step_key'] ?? null,
            'notes' => $notes,
            'created_by' => $userId,
            'started_at' => $startedAt,
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->recordEvent($id, $firstStep['id'] ?? null, 'session_created', $status, 'Demo session created.', ['audience' => $audience], $userId);
        $this->audit('demo_session_created', 'demo_session', $id, sprintf('Demo session %s created.', $code), ['status' => $status, 'mode_id' => $modeId], $userId);
        return $this->requireSession($id);
    }

    /** @return list<array<string, mixed>> */
    public function sessions(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT s.*, m.mode_key, m.name AS mode_name FROM platform_demo_sessions s JOIN platform_demo_modes m ON m.id = s.mode_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE s.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY s.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function advanceSession(string $id, array $body, ?string $userId): array
    {
        $session = $this->requireSession($id);
        $outcome = trim((string) ($body['outcome'] ?? 'step_completed')) ?: 'step_completed';
        $notes = trim((string) ($body['notes'] ?? '')) ?: 'Walkthrough step completed.';
        $stepKey = trim((string) ($body['step_key'] ?? ($session['current_step_key'] ?? '')));
        $step = $stepKey !== '' ? $this->findStepByKey((string) $session['mode_id'], $stepKey) : null;
        if (!$step) {
            $step = $this->firstStepForMode((string) $session['mode_id']);
        }
        $nextStep = $this->nextStepForMode((string) $session['mode_id'], (int) ($step['sort_order'] ?? 0));
        $now = gmdate('c');
        $newStatus = $nextStep ? 'running' : 'completed';
        $startedAt = $session['started_at'] ?: $now;
        $completedAt = $newStatus === 'completed' ? $now : null;
        $stmt = $this->pdo->prepare('UPDATE platform_demo_sessions SET status = :status, current_step_key = :current_step_key, started_at = :started_at, completed_at = :completed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $newStatus,
            'current_step_key' => $nextStep['step_key'] ?? $step['step_key'] ?? null,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'updated_at' => $now,
            'id' => $id,
        ]);
        $this->recordEvent($id, $step['id'] ?? null, 'step_advanced', $outcome, $notes, ['next_step_key' => $nextStep['step_key'] ?? null, 'session_status' => $newStatus], $userId);
        $this->audit('demo_session_advanced', 'demo_session', $id, 'Guided demo session advanced.', ['step_key' => $step['step_key'] ?? null, 'status' => $newStatus], $userId);
        return $this->requireSession($id);
    }

    /** @return list<array<string, mixed>> */
    public function sessionEvents(?string $sessionId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT e.*, s.session_code, w.step_key, w.title AS step_title FROM platform_demo_session_events e JOIN platform_demo_sessions s ON s.id = e.session_id LEFT JOIN platform_guided_walkthrough_steps w ON w.id = e.step_id';
        $params = [];
        if ($sessionId !== null && $sessionId !== '') {
            $sql .= ' WHERE e.session_id = :session_id';
            $params['session_id'] = $sessionId;
        }
        $sql .= ' ORDER BY e.created_at DESC LIMIT :limit';
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
    public function recordFeedback(array $body, ?string $userId): array
    {
        $sessionId = trim((string) ($body['session_id'] ?? ''));
        if ($sessionId === '') {
            throw new ValidationException(['session_id' => ['session_id is required.']]);
        }
        $this->requireSession($sessionId);
        $rating = max(0, min(10, (int) ($body['rating'] ?? 0)));
        $signal = trim((string) ($body['signal'] ?? 'neutral')) ?: 'neutral';
        if (!in_array($signal, ['positive', 'neutral', 'concern', 'blocker'], true)) {
            throw new ValidationException(['signal' => ['Signal must be positive, neutral, concern or blocker.']]);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_demo_feedback (id, session_id, audience_type, rating, signal, notes, next_action, created_by, created_at) VALUES (:id, :session_id, :audience_type, :rating, :signal, :notes, :next_action, :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'session_id' => $sessionId,
            'audience_type' => trim((string) ($body['audience_type'] ?? 'investor')) ?: 'investor',
            'rating' => $rating,
            'signal' => $signal,
            'notes' => trim((string) ($body['notes'] ?? '')),
            'next_action' => trim((string) ($body['next_action'] ?? '')),
            'created_by' => $userId,
            'created_at' => $now,
        ]);
        $this->audit('demo_feedback_recorded', 'demo_feedback', $id, 'Demo feedback recorded.', ['signal' => $signal, 'rating' => $rating], $userId);
        return $this->requireFeedback($id);
    }

    /** @return list<array<string, mixed>> */
    public function feedback(?string $sessionId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT f.*, s.session_code FROM platform_demo_feedback f JOIN platform_demo_sessions s ON s.id = f.session_id';
        $params = [];
        if ($sessionId !== null && $sessionId !== '') { $sql .= ' WHERE f.session_id = :session_id'; $params['session_id'] = $sessionId; }
        $sql .= ' ORDER BY f.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function evaluateReadiness(array $body, ?string $userId): array
    {
        $checks = $this->readinessChecklist();
        $passed = count(array_filter($checks, static fn(array $check): bool => (bool) $check['passed']));
        $score = (int) round(($passed / max(1, count($checks))) * 100);
        $blockers = array_values(array_map(static fn(array $check): string => (string) $check['label'], array_filter($checks, static fn(array $check): bool => !$check['passed'])));
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'DEMO-READY-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $level = $score >= 80 ? 'demo_ready_with_caveats' : 'needs_operator_review';
        $stmt = $this->pdo->prepare('INSERT INTO platform_demo_readiness_reviews (id, review_code, status, readiness_level, score, checklist_json, blockers_json, recommended_script_json, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :review_code, :status, :readiness_level, :score, :checklist_json, :blockers_json, :recommended_script_json, :notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'review_code' => $code,
            'status' => 'open',
            'readiness_level' => $level,
            'score' => $score,
            'checklist_json' => json_encode($checks, JSON_THROW_ON_ERROR),
            'blockers_json' => json_encode($blockers, JSON_THROW_ON_ERROR),
            'recommended_script_json' => json_encode($this->recommendedScript(), JSON_THROW_ON_ERROR),
            'notes' => trim((string) ($body['notes'] ?? 'Step 40 demo readiness evaluation.')),
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        $this->audit('demo_readiness_evaluated', 'demo_readiness_review', $id, 'Demo readiness review generated.', ['score' => $score, 'blockers' => $blockers], $userId);
        return $this->requireReadinessReview($id);
    }

    /** @return list<array<string, mixed>> */
    public function readinessReviews(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_demo_readiness_reviews';
        $params = [];
        if ($status === 'active') {
            $sql .= " WHERE status IN ('open','needs_work')";
        } elseif ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeReview'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function reviewReadiness(string $id, array $body, ?string $userId): array
    {
        $decision = trim((string) ($body['decision'] ?? 'reviewed_with_caveats')) ?: 'reviewed_with_caveats';
        $status = match ($decision) {
            'approve', 'approved' => 'approved',
            'needs_work' => 'needs_work',
            'archive', 'archived' => 'archived',
            default => 'reviewed',
        };
        $notes = trim((string) ($body['notes'] ?? 'Demo readiness reviewed.'));
        $stmt = $this->pdo->prepare('UPDATE platform_demo_readiness_reviews SET status = :status, notes = :notes, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $now = gmdate('c');
        $stmt->execute(['status' => $status, 'notes' => $notes, 'reviewed_by' => $userId, 'reviewed_at' => $now, 'updated_at' => $now, 'id' => $id]);
        if ($stmt->rowCount() < 1) { throw new ValidationException(['id' => ['Demo readiness review was not found.']]); }
        $this->audit('demo_readiness_reviewed', 'demo_readiness_review', $id, 'Demo readiness review completed.', ['status' => $status, 'decision' => $decision], $userId);
        return $this->requireReadinessReview($id);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_demo_walkthrough_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array { $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}'); unset($row['metadata_json']); return $row; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function count(string $table, string $where = '1 = 1'): int
    {
        return (int) $this->pdo->query(sprintf('SELECT COUNT(*) FROM %s WHERE %s', $table, $where))->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function latestSession(): ?array
    {
        $stmt = $this->pdo->query('SELECT s.*, m.mode_key, m.name AS mode_name FROM platform_demo_sessions s JOIN platform_demo_modes m ON m.id = s.mode_id ORDER BY s.created_at DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function defaultModeId(): string
    {
        $id = $this->pdo->query("SELECT id FROM platform_demo_modes WHERE mode_key = 'investor_walkthrough' LIMIT 1")->fetchColumn();
        if (!$id) { throw new ValidationException(['mode_id' => ['No default demo mode is available. Run migrations.']]); }
        return (string) $id;
    }

    /** @return array<string, mixed> */
    private function requireMode(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_demo_modes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['mode_id' => ['Demo mode was not found.']]); }
        return $row;
    }

    /** @return array<string, mixed> */
    private function firstStepForMode(string $modeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_guided_walkthrough_steps WHERE mode_id = :mode_id AND status = 'active' ORDER BY sort_order ASC LIMIT 1");
        $stmt->execute(['mode_id' => $modeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['mode_id' => ['Demo mode has no active walkthrough steps.']]); }
        return $row;
    }

    /** @return array<string, mixed>|null */
    private function nextStepForMode(string $modeId, int $currentSortOrder): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_guided_walkthrough_steps WHERE mode_id = :mode_id AND status = 'active' AND sort_order > :sort_order ORDER BY sort_order ASC LIMIT 1");
        $stmt->bindValue('mode_id', $modeId);
        $stmt->bindValue('sort_order', $currentSortOrder, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findStepByKey(string $modeId, string $stepKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_guided_walkthrough_steps WHERE mode_id = :mode_id AND step_key = :step_key LIMIT 1');
        $stmt->execute(['mode_id' => $modeId, 'step_key' => $stepKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function requireSession(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT s.*, m.mode_key, m.name AS mode_name FROM platform_demo_sessions s JOIN platform_demo_modes m ON m.id = s.mode_id WHERE s.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Demo session was not found.']]); }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireFeedback(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT f.*, s.session_code FROM platform_demo_feedback f JOIN platform_demo_sessions s ON s.id = f.session_id WHERE f.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Demo feedback was not found.']]); }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireReadinessReview(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_demo_readiness_reviews WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Demo readiness review was not found.']]); }
        return $this->normalizeReview($row);
    }

    /** @return list<array<string, mixed>> */
    private function readinessChecklist(): array
    {
        return [
            ['key' => 'demo_modes', 'label' => 'At least one active demo mode exists.', 'passed' => $this->count('platform_demo_modes', "status = 'active'") > 0],
            ['key' => 'walkthrough_steps', 'label' => 'Guided investor walkthrough has at least six active steps.', 'passed' => $this->count('platform_guided_walkthrough_steps', "status = 'active'") >= 6],
            ['key' => 'investor_narrative', 'label' => 'Investor reporting narrative sections are available.', 'passed' => $this->tableCountIfExists('platform_demo_narrative_sections', "status = 'active'") > 0],
            ['key' => 'sustainability', 'label' => 'Sustainability impact factors are available with caveats.', 'passed' => $this->tableCountIfExists('platform_sustainability_factors', "status = 'active'") > 0],
            ['key' => 'quality_gate', 'label' => 'Step 39 release evidence script is present.', 'passed' => is_file(dirname(__DIR__, 3) . '/scripts/ci-release-evidence.ps1')],
        ];
    }

    private function tableCountIfExists(string $table, string $where = '1 = 1'): int
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);
        if (!$stmt->fetchColumn()) { return 0; }
        return $this->count($table, $where);
    }

    /** @return list<string> */
    private function recommendedScript(): array
    {
        return [
            'Open with: the user does not want an STL, the object must work again.',
            'Walk through intake, AI governance, repair path, geometry, provider routing and dispatch proof.',
            'Close with customer acceptance, sustainability estimates and investor KPI caveats.',
            'Explicitly say which parts are live local workflow, mock, governance-only or future production integration.',
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeReview(array $row): array
    {
        $row['score'] = (int) $row['score'];
        $row['checklist'] = $this->decodeJson($row['checklist_json'] ?? '[]');
        $row['blockers'] = $this->decodeJson($row['blockers_json'] ?? '[]');
        $row['recommended_script'] = $this->decodeJson($row['recommended_script_json'] ?? '[]');
        unset($row['checklist_json'], $row['blockers_json'], $row['recommended_script_json']);
        return $row;
    }

    /** @param array<string, mixed> $metadata */
    private function recordEvent(string $sessionId, ?string $stepId, string $eventType, string $outcome, string $notes, array $metadata, ?string $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_demo_session_events (id, session_id, step_id, event_type, outcome, notes, metadata_json, created_by, created_at) VALUES (:id, :session_id, :step_id, :event_type, :outcome, :notes, :metadata_json, :created_by, :created_at)');
        $stmt->execute(['id' => Uuid::v4(), 'session_id' => $sessionId, 'step_id' => $stepId, 'event_type' => $eventType, 'outcome' => $outcome, 'notes' => $notes, 'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_by' => $userId, 'created_at' => gmdate('c')]);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_demo_walkthrough_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute(['id' => Uuid::v4(), 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId, 'message' => $message, 'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_by' => $userId, 'created_at' => gmdate('c')]);
    }

    private function decodeJson(?string $json): mixed
    {
        $decoded = json_decode($json ?: 'null', true);
        return $decoded ?? [];
    }
}
