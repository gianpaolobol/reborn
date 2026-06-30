<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class PilotLaunchService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $latestDecision = $this->latestDecision();
        return [
            'summary' => [
                'ready_data_room_assets' => $this->count('platform_demo_data_room_assets', "status = 'ready'"),
                'total_data_room_assets' => $this->count('platform_demo_data_room_assets'),
                'ready_checklist_items' => $this->count('platform_pilot_launch_checklist_items', "status IN ('ready','done','approved')"),
                'open_checklist_items' => $this->count('platform_pilot_launch_checklist_items', "status IN ('open','needs_work','blocked')"),
                'critical_open_items' => $this->count('platform_pilot_launch_checklist_items', "priority = 'critical' AND status NOT IN ('ready','done','approved','waived')"),
                'feedback_loops' => $this->count('platform_stakeholder_feedback_loops'),
                'open_feedback_items' => $this->count('platform_stakeholder_feedback_items', "status IN ('open','triaged')"),
                'post_demo_reports' => $this->count('platform_post_demo_reports'),
                'latest_decision' => (string) ($latestDecision['decision'] ?? 'none'),
                'latest_decision_score' => (int) ($latestDecision['score'] ?? 0),
            ],
            'data_room_assets' => $this->dataRoomAssets('all', 8),
            'pilot_checklist' => $this->pilotChecklist('all', 12),
            'feedback_loops' => $this->feedbackLoops('all', 8),
            'feedback_items' => $this->stakeholderFeedback('', 8),
            'post_demo_reports' => $this->postDemoReports('all', 8),
            'go_no_go_decisions' => $this->goNoGoDecisions('all', 5),
            'scope_note' => 'Step 41 creates a data room, pilot launch checklist and stakeholder feedback loop for controlled demos. It does not approve production launch, real payments, real fulfilment, legal warranty terms or public sustainability claims.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function dataRoomAssets(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_demo_data_room_assets';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY category ASC, title ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn (array $row): array => $this->normalizeDataRoomAsset($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createDataRoomAsset(array $body, ?string $userId): array
    {
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(['title' => ['title is required.']]);
        }
        $category = trim((string) ($body['category'] ?? 'pilot')) ?: 'pilot';
        $audience = trim((string) ($body['audience'] ?? 'investor')) ?: 'investor';
        $status = trim((string) ($body['status'] ?? 'draft')) ?: 'draft';
        if (!in_array($status, ['draft', 'ready', 'needs_review', 'archived'], true)) {
            throw new ValidationException(['status' => ['Status must be draft, ready, needs_review or archived.']]);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $assetKey = $this->slug((string) ($body['asset_key'] ?? $title)) . '-' . substr(str_replace('-', '', $id), 0, 6);
        $stmt = $this->pdo->prepare('INSERT INTO platform_demo_data_room_assets (id, asset_key, title, category, audience, status, sensitivity, route_hint, source_endpoint, summary, caveat, owner, last_verified_at, metadata_json, created_at, updated_at) VALUES (:id, :asset_key, :title, :category, :audience, :status, :sensitivity, :route_hint, :source_endpoint, :summary, :caveat, :owner, :last_verified_at, :metadata_json, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'asset_key' => $assetKey,
            'title' => $title,
            'category' => $category,
            'audience' => $audience,
            'status' => $status,
            'sensitivity' => trim((string) ($body['sensitivity'] ?? 'internal')) ?: 'internal',
            'route_hint' => trim((string) ($body['route_hint'] ?? '')),
            'source_endpoint' => trim((string) ($body['source_endpoint'] ?? '')),
            'summary' => trim((string) ($body['summary'] ?? '')),
            'caveat' => trim((string) ($body['caveat'] ?? 'Internal/pilot asset only.')),
            'owner' => trim((string) ($body['owner'] ?? 'operator')) ?: 'operator',
            'last_verified_at' => $status === 'ready' ? $now : null,
            'metadata_json' => $this->encode($body['metadata'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('data_room_asset_created', 'data_room_asset', $id, sprintf('Data room asset %s created.', $title), ['status' => $status, 'category' => $category], $userId);
        return $this->requireDataRoomAsset($id);
    }

    /** @return list<array<string, mixed>> */
    public function pilotChecklist(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_pilot_launch_checklist_items';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= " ORDER BY CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END ASC, category ASC, title ASC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function updateChecklistStatus(string $id, array $body, ?string $userId): array
    {
        $item = $this->requireChecklistItem($id);
        $status = trim((string) ($body['status'] ?? 'ready')) ?: 'ready';
        if (!in_array($status, ['open', 'ready', 'done', 'approved', 'needs_work', 'blocked', 'waived'], true)) {
            throw new ValidationException(['status' => ['Status must be open, ready, done, approved, needs_work, blocked or waived.']]);
        }
        $notes = trim((string) ($body['notes'] ?? $item['notes'])) ?: (string) $item['notes'];
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_pilot_launch_checklist_items SET status = :status, notes = :notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $status, 'notes' => $notes, 'updated_at' => $now, 'id' => $id]);
        $this->audit('pilot_checklist_status_updated', 'pilot_checklist_item', $id, sprintf('Pilot checklist item %s moved to %s.', $item['item_key'], $status), ['previous_status' => $item['status']], $userId);
        return $this->requireChecklistItem($id);
    }

    /** @return array<string, mixed> */
    public function evaluatePilotLaunch(array $body, ?string $userId): array
    {
        $score = $this->pilotScore();
        $blockers = $this->pilotBlockers();
        $conditions = $this->pilotConditions();
        $decision = $blockers === [] && $score >= 82 ? 'go' : ($score >= 62 ? 'conditional_go' : 'no_go');
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'PILOT-GATE-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $rationale = trim((string) ($body['rationale'] ?? 'Automated Step 41 pilot launch evaluation from checklist, data room and stakeholder feedback state.'));
        $stmt = $this->pdo->prepare('INSERT INTO platform_pilot_go_no_go_decisions (id, decision_code, status, decision, score, rationale, conditions_json, blockers_json, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :decision_code, :status, :decision, :score, :rationale, :conditions_json, :blockers_json, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'decision_code' => $code,
            'status' => 'draft',
            'decision' => $decision,
            'score' => $score,
            'rationale' => $rationale,
            'conditions_json' => $this->encode($conditions),
            'blockers_json' => $this->encode($blockers),
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        $this->audit('pilot_launch_evaluated', 'pilot_go_no_go_decision', $id, sprintf('Pilot launch evaluated as %s with score %s.', $decision, $score), ['blockers' => $blockers, 'conditions' => $conditions], $userId);
        return $this->requireDecision($id);
    }

    /** @return list<array<string, mixed>> */
    public function feedbackLoops(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_stakeholder_feedback_loops';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function createFeedbackLoop(array $body, ?string $userId): array
    {
        $audience = trim((string) ($body['audience_type'] ?? 'investor')) ?: 'investor';
        $objective = trim((string) ($body['objective'] ?? 'Collect structured stakeholder feedback on the guided demo and pilot launch proposal.'));
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'FEEDBACK-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $stmt = $this->pdo->prepare('INSERT INTO platform_stakeholder_feedback_loops (id, loop_code, audience_type, stakeholder_name, status, objective, related_demo_session_id, scheduled_at, notes, created_by, created_at, updated_at, closed_at) VALUES (:id, :loop_code, :audience_type, :stakeholder_name, :status, :objective, :related_demo_session_id, :scheduled_at, :notes, :created_by, :created_at, :updated_at, :closed_at)');
        $stmt->execute([
            'id' => $id,
            'loop_code' => $code,
            'audience_type' => $audience,
            'stakeholder_name' => trim((string) ($body['stakeholder_name'] ?? 'Stakeholder')),
            'status' => 'open',
            'objective' => $objective,
            'related_demo_session_id' => trim((string) ($body['related_demo_session_id'] ?? '')) ?: null,
            'scheduled_at' => trim((string) ($body['scheduled_at'] ?? '')) ?: null,
            'notes' => trim((string) ($body['notes'] ?? '')),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'closed_at' => null,
        ]);
        $this->audit('feedback_loop_created', 'stakeholder_feedback_loop', $id, sprintf('Stakeholder feedback loop %s created.', $code), ['audience_type' => $audience], $userId);
        return $this->requireFeedbackLoop($id);
    }

    /** @return list<array<string, mixed>> */
    public function stakeholderFeedback(string $loopId = '', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT f.*, l.loop_code, l.stakeholder_name FROM platform_stakeholder_feedback_items f JOIN platform_stakeholder_feedback_loops l ON l.id = f.loop_id';
        $params = [];
        if ($loopId !== '') {
            $sql .= ' WHERE f.loop_id = :loop_id';
            $params['loop_id'] = $loopId;
        }
        $sql .= ' ORDER BY f.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['rating'] = (int) ($row['rating'] ?? 0);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function recordStakeholderFeedback(array $body, ?string $userId): array
    {
        $loopId = trim((string) ($body['loop_id'] ?? '')) ?: (string) ($this->latestFeedbackLoop()['id'] ?? '');
        if ($loopId === '') {
            throw new ValidationException(['loop_id' => ['loop_id is required when no feedback loop exists.']]);
        }
        $this->requireFeedbackLoop($loopId);
        $signal = trim((string) ($body['signal'] ?? 'neutral')) ?: 'neutral';
        if (!in_array($signal, ['positive', 'neutral', 'concern', 'blocker'], true)) {
            throw new ValidationException(['signal' => ['Signal must be positive, neutral, concern or blocker.']]);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_stakeholder_feedback_items (id, loop_id, audience_type, signal, rating, topic, notes, requested_action, status, created_by, created_at, updated_at) VALUES (:id, :loop_id, :audience_type, :signal, :rating, :topic, :notes, :requested_action, :status, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'loop_id' => $loopId,
            'audience_type' => trim((string) ($body['audience_type'] ?? 'investor')) ?: 'investor',
            'signal' => $signal,
            'rating' => max(0, min(10, (int) ($body['rating'] ?? 0))),
            'topic' => trim((string) ($body['topic'] ?? 'general')) ?: 'general',
            'notes' => trim((string) ($body['notes'] ?? '')),
            'requested_action' => trim((string) ($body['requested_action'] ?? '')),
            'status' => 'open',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('stakeholder_feedback_recorded', 'stakeholder_feedback_item', $id, sprintf('Stakeholder feedback recorded with signal %s.', $signal), ['loop_id' => $loopId], $userId);
        return $this->requireStakeholderFeedback($id);
    }

    /** @return list<array<string, mixed>> */
    public function postDemoReports(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*, l.loop_code FROM platform_post_demo_reports r LEFT JOIN platform_stakeholder_feedback_loops l ON l.id = r.loop_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE r.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn (array $row): array => $this->normalizePostDemoReport($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createPostDemoReport(array $body, ?string $userId): array
    {
        $loopId = trim((string) ($body['loop_id'] ?? '')) ?: (string) ($this->latestFeedbackLoop()['id'] ?? '');
        $feedback = $loopId !== '' ? $this->stakeholderFeedback($loopId, 50) : [];
        $positives = array_values(array_map(fn (array $item): string => (string) $item['notes'], array_filter($feedback, fn (array $item): bool => $item['signal'] === 'positive')));
        $concerns = array_values(array_map(fn (array $item): string => (string) $item['notes'], array_filter($feedback, fn (array $item): bool => in_array($item['signal'], ['concern', 'blocker'], true))));
        if ($positives === []) { $positives = ['Guided demo narrative and governance evidence are understandable.']; }
        if ($concerns === []) { $concerns = ['Provider, legal, payments and production deployment remain to be validated before beta launch.']; }
        $actions = ['Validate latest CI quality gate', 'Triangulate stakeholder feedback', 'Review pilot provider/legal readiness before go/no-go'];
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'POST-DEMO-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $stmt = $this->pdo->prepare('INSERT INTO platform_post_demo_reports (id, report_code, loop_id, demo_session_id, status, executive_summary, positives_json, concerns_json, follow_up_actions_json, created_by, created_at, updated_at, published_at) VALUES (:id, :report_code, :loop_id, :demo_session_id, :status, :executive_summary, :positives_json, :concerns_json, :follow_up_actions_json, :created_by, :created_at, :updated_at, :published_at)');
        $stmt->execute([
            'id' => $id,
            'report_code' => $code,
            'loop_id' => $loopId !== '' ? $loopId : null,
            'demo_session_id' => trim((string) ($body['demo_session_id'] ?? '')) ?: null,
            'status' => trim((string) ($body['status'] ?? 'draft')) ?: 'draft',
            'executive_summary' => trim((string) ($body['executive_summary'] ?? 'Post-demo report generated from stakeholder feedback loop.')),
            'positives_json' => $this->encode($body['positives'] ?? $positives),
            'concerns_json' => $this->encode($body['concerns'] ?? $concerns),
            'follow_up_actions_json' => $this->encode($body['follow_up_actions'] ?? $actions),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => null,
        ]);
        $this->audit('post_demo_report_created', 'post_demo_report', $id, sprintf('Post-demo report %s created.', $code), ['loop_id' => $loopId], $userId);
        return $this->requirePostDemoReport($id);
    }

    /** @return list<array<string, mixed>> */
    public function goNoGoDecisions(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_pilot_go_no_go_decisions';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn (array $row): array => $this->normalizeDecision($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_pilot_launch_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decode($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function pilotScore(): int
    {
        $total = max(1, $this->count('platform_pilot_launch_checklist_items'));
        $ready = $this->count('platform_pilot_launch_checklist_items', "status IN ('ready','done','approved','waived')");
        $readyAssets = $this->count('platform_demo_data_room_assets', "status = 'ready'");
        $positiveFeedback = $this->count('platform_stakeholder_feedback_items', "signal = 'positive'");
        $blockers = $this->count('platform_stakeholder_feedback_items', "signal = 'blocker'") + $this->count('platform_pilot_launch_checklist_items', "status = 'blocked'");
        $score = (int) round(35 + (($ready / $total) * 35) + min(15, $readyAssets * 3) + min(10, $positiveFeedback * 2) - min(25, $blockers * 8));
        return max(0, min(100, $score));
    }

    /** @return list<string> */
    private function pilotBlockers(): array
    {
        $blockers = [];
        foreach ($this->pilotChecklist('all', 100) as $item) {
            if ($item['priority'] === 'critical' && !in_array($item['status'], ['ready', 'done', 'approved', 'waived'], true)) {
                $blockers[] = sprintf('%s: %s', $item['category'], $item['title']);
            }
        }
        if ($this->count('platform_stakeholder_feedback_items', "signal = 'blocker'") > 0) {
            $blockers[] = 'Stakeholder blocker feedback must be resolved.';
        }
        return $blockers;
    }

    /** @return list<string> */
    private function pilotConditions(): array
    {
        return [
            'Latest Step 40 and Step 41 CI evidence must pass on the current commit SHA.',
            'Every external demo must disclose that AI, payment, fulfilment, warranty and sustainability flows are still mock or pilot-only.',
            'Provider shortlist, legal/privacy terms and customer support workflow must be reviewed before any private beta commitment.',
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestDecision(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM platform_pilot_go_no_go_decisions ORDER BY created_at DESC LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ? $this->normalizeDecision($row) : null;
    }

    /** @return array<string, mixed>|null */
    private function latestFeedbackLoop(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM platform_stakeholder_feedback_loops ORDER BY created_at DESC LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function requireDataRoomAsset(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_demo_data_room_assets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Data room asset was not found.']]); }
        return $this->normalizeDataRoomAsset($row);
    }

    /** @return array<string, mixed> */
    private function requireChecklistItem(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_pilot_launch_checklist_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Pilot checklist item was not found.']]); }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireFeedbackLoop(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_stakeholder_feedback_loops WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Stakeholder feedback loop was not found.']]); }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireStakeholderFeedback(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT f.*, l.loop_code, l.stakeholder_name FROM platform_stakeholder_feedback_items f JOIN platform_stakeholder_feedback_loops l ON l.id = f.loop_id WHERE f.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Stakeholder feedback item was not found.']]); }
        $row['rating'] = (int) ($row['rating'] ?? 0);
        return $row;
    }

    /** @return array<string, mixed> */
    private function requirePostDemoReport(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, l.loop_code FROM platform_post_demo_reports r LEFT JOIN platform_stakeholder_feedback_loops l ON l.id = r.loop_id WHERE r.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Post-demo report was not found.']]); }
        return $this->normalizePostDemoReport($row);
    }

    /** @return array<string, mixed> */
    private function requireDecision(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_pilot_go_no_go_decisions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new ValidationException(['id' => ['Pilot go/no-go decision was not found.']]); }
        return $this->normalizeDecision($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeDataRoomAsset(array $row): array
    {
        $row['metadata'] = $this->decode($row['metadata_json'] ?? '{}');
        unset($row['metadata_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePostDemoReport(array $row): array
    {
        $row['positives'] = $this->decode($row['positives_json'] ?? '[]');
        $row['concerns'] = $this->decode($row['concerns_json'] ?? '[]');
        $row['follow_up_actions'] = $this->decode($row['follow_up_actions_json'] ?? '[]');
        unset($row['positives_json'], $row['concerns_json'], $row['follow_up_actions_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeDecision(array $row): array
    {
        $row['score'] = (int) ($row['score'] ?? 0);
        $row['conditions'] = $this->decode($row['conditions_json'] ?? '[]');
        $row['blockers'] = $this->decode($row['blockers_json'] ?? '[]');
        unset($row['conditions_json'], $row['blockers_json']);
        return $row;
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_pilot_launch_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
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

    private function slug(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $value), '_'));
        return $slug !== '' ? $slug : 'asset';
    }
}
