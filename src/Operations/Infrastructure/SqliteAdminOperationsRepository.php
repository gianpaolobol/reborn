<?php

declare(strict_types=1);

namespace Reborn\Operations\Infrastructure;

use PDO;
use Reborn\Identity\Domain\User;
use Reborn\Operations\Domain\AdminOperationsRepository;
use Reborn\Operations\Domain\OpsEscalation;
use Reborn\Operations\Domain\OpsModerationAction;
use Reborn\Operations\Domain\OpsReviewItem;
use Reborn\Shared\Support\Uuid;

final class SqliteAdminOperationsRepository implements AdminOperationsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createReviewItem(User $actor, array $payload): OpsReviewItem
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $sourceType = $this->sourceType($payload['source_type'] ?? 'manual');
        $sourceId = $this->trimmed($payload['source_id'] ?? $id, 120, $id);
        $category = $this->category($payload['category'] ?? 'manual_review');
        $priority = $this->priority($payload['priority'] ?? 'medium');
        $title = $this->trimmed($payload['title'] ?? 'Operations review item', 180, 'Operations review item');
        $description = $this->trimmed($payload['description'] ?? 'Review item created from admin operations console.', 1600, 'Review item created from admin operations console.');
        $repairCaseId = $this->nullableString($payload['repair_case_id'] ?? null);
        $providerId = $this->nullableString($payload['provider_id'] ?? null);
        $payloadJson = is_array($payload['payload'] ?? null) ? $payload['payload'] : [
            'source' => 'admin_operations_console',
            'initial_status' => 'open',
        ];

        $stmt = $this->pdo->prepare('INSERT INTO ops_review_items (id, source_type, source_id, repair_case_id, provider_id, category, priority, status, title, description, payload_json, assigned_to, created_by, created_at, updated_at, resolved_at) VALUES (:id, :source_type, :source_id, :repair_case_id, :provider_id, :category, :priority, :status, :title, :description, :payload_json, :assigned_to, :created_by, :created_at, :updated_at, :resolved_at)');
        $stmt->execute([
            'id' => $id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'repair_case_id' => $repairCaseId,
            'provider_id' => $providerId,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
            'title' => $title,
            'description' => $description,
            'payload_json' => json_encode($payloadJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'assigned_to' => null,
            'created_by' => $actor->id,
            'created_at' => $now,
            'updated_at' => $now,
            'resolved_at' => null,
        ]);

        return $this->findReviewItem($id) ?? throw new \RuntimeException('Operations review item creation failed.');
    }

    public function listReviewItems(?string $status = null, ?string $priority = null): array
    {
        $conditions = [];
        $params = [];
        if ($status !== null && $status !== '') {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($priority !== null && $priority !== '') {
            $conditions[] = 'priority = :priority';
            $params['priority'] = $priority;
        }
        $sql = 'SELECT * FROM ops_review_items';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn(array $row): OpsReviewItem => OpsReviewItem::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findReviewItem(string $id): ?OpsReviewItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ops_review_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? OpsReviewItem::fromRow($row) : null;
    }

    public function assignReviewItem(string $id, User $actor, ?string $assignedTo): OpsReviewItem
    {
        $now = gmdate('c');
        $assignedTo = $assignedTo !== null && trim($assignedTo) !== '' ? trim($assignedTo) : $actor->id;
        $stmt = $this->pdo->prepare("UPDATE ops_review_items SET assigned_to = :assigned_to, status = CASE WHEN status = 'open' THEN 'in_review' ELSE status END, updated_at = :updated_at WHERE id = :id");
        $stmt->execute(['assigned_to' => $assignedTo, 'updated_at' => $now, 'id' => $id]);

        return $this->findReviewItem($id) ?? throw new \RuntimeException('Operations review item not found after assignment.');
    }

    public function recordModerationAction(string $reviewItemId, User $actor, array $payload): OpsModerationAction
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $reviewItem = $this->findReviewItem($reviewItemId) ?? throw new \RuntimeException('Review item not found.');
        $actionType = $this->actionType($payload['action_type'] ?? 'policy_note');
        $targetType = $this->targetType($payload['target_type'] ?? $reviewItem->sourceType);
        $targetId = $this->trimmed($payload['target_id'] ?? $reviewItem->sourceId, 120, $reviewItem->sourceId);
        $reason = $this->trimmed($payload['reason'] ?? 'Moderation action recorded.', 1200, 'Moderation action recorded.');
        $actionPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : ['source' => 'admin_operations_console'];

        $stmt = $this->pdo->prepare('INSERT INTO ops_moderation_actions (id, review_item_id, action_type, target_type, target_id, status, reason, payload_json, created_by, created_at) VALUES (:id, :review_item_id, :action_type, :target_type, :target_id, :status, :reason, :payload_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'review_item_id' => $reviewItemId,
            'action_type' => $actionType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'status' => 'recorded',
            'reason' => $reason,
            'payload_json' => json_encode($actionPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $actor->id,
            'created_at' => $now,
        ]);
        $this->pdo->prepare("UPDATE ops_review_items SET status = CASE WHEN status = 'open' THEN 'in_review' ELSE status END, updated_at = :updated_at WHERE id = :id")->execute(['updated_at' => $now, 'id' => $reviewItemId]);

        return $this->findModerationAction($id) ?? throw new \RuntimeException('Moderation action creation failed.');
    }

    public function listModerationActions(string $reviewItemId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ops_moderation_actions WHERE review_item_id = :review_item_id ORDER BY created_at DESC');
        $stmt->execute(['review_item_id' => $reviewItemId]);

        return array_map(static fn(array $row): OpsModerationAction => OpsModerationAction::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createEscalation(string $reviewItemId, User $actor, array $payload): OpsEscalation
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $level = $this->escalationLevel($payload['escalation_level'] ?? 'ops_lead');
        $reason = $this->trimmed($payload['reason'] ?? 'Escalated for operational review.', 1200, 'Escalated for operational review.');
        $assignedTo = $this->nullableString($payload['assigned_to'] ?? null);
        $stmt = $this->pdo->prepare('INSERT INTO ops_escalations (id, review_item_id, escalation_level, status, reason, assigned_to, created_by, created_at, resolved_at) VALUES (:id, :review_item_id, :escalation_level, :status, :reason, :assigned_to, :created_by, :created_at, :resolved_at)');
        $stmt->execute([
            'id' => $id,
            'review_item_id' => $reviewItemId,
            'escalation_level' => $level,
            'status' => 'open',
            'reason' => $reason,
            'assigned_to' => $assignedTo,
            'created_by' => $actor->id,
            'created_at' => $now,
            'resolved_at' => null,
        ]);
        $this->pdo->prepare("UPDATE ops_review_items SET status = 'escalated', updated_at = :updated_at WHERE id = :id")->execute(['updated_at' => $now, 'id' => $reviewItemId]);

        return $this->findEscalation($id) ?? throw new \RuntimeException('Operations escalation creation failed.');
    }

    public function listEscalations(?string $status = null): array
    {
        if ($status !== null && $status !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM ops_escalations WHERE status = :status ORDER BY created_at DESC');
            $stmt->execute(['status' => $status]);
        } else {
            $stmt = $this->pdo->query('SELECT * FROM ops_escalations ORDER BY created_at DESC');
        }

        return array_map(static fn(array $row): OpsEscalation => OpsEscalation::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function resolveReviewItem(string $id, User $actor, array $payload): OpsReviewItem
    {
        $now = gmdate('c');
        $resolution = $this->trimmed($payload['resolution'] ?? 'resolved', 120, 'resolved');
        $existing = $this->findReviewItem($id) ?? throw new \RuntimeException('Review item not found.');
        $mergedPayload = $existing->payload + ['resolution' => $resolution, 'resolved_by' => $actor->id];
        $stmt = $this->pdo->prepare("UPDATE ops_review_items SET status = 'resolved', payload_json = :payload_json, updated_at = :updated_at, resolved_at = :resolved_at WHERE id = :id");
        $stmt->execute([
            'payload_json' => json_encode($mergedPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'resolved_at' => $now,
            'id' => $id,
        ]);

        return $this->findReviewItem($id) ?? throw new \RuntimeException('Review item not found after resolve.');
    }

    public function audit(User $actor, string $action, string $subjectType, string $subjectId, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO ops_audit_log (id, actor_id, action, subject_type, subject_id, payload_json, created_at) VALUES (:id, :actor_id, :action, :subject_type, :subject_id, :payload_json, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => gmdate('c'),
        ]);
    }

    public function summary(): array
    {
        $countsByStatus = $this->counts('ops_review_items', 'status');
        $countsByPriority = $this->counts('ops_review_items', 'priority');
        $openEscalations = (int) $this->pdo->query("SELECT COUNT(*) FROM ops_escalations WHERE status = 'open'")->fetchColumn();
        $moderationActions = (int) $this->pdo->query('SELECT COUNT(*) FROM ops_moderation_actions')->fetchColumn();
        $auditEvents = (int) $this->pdo->query('SELECT COUNT(*) FROM ops_audit_log')->fetchColumn();

        return [
            'review_items' => array_sum($countsByStatus),
            'review_items_by_status' => $countsByStatus,
            'review_items_by_priority' => $countsByPriority,
            'open_escalations' => $openEscalations,
            'moderation_actions' => $moderationActions,
            'audit_events' => $auditEvents,
            'sla_policy' => [
                'critical' => '4 business hours',
                'high' => '1 business day',
                'medium' => '3 business days',
                'low' => '7 business days',
            ],
        ];
    }

    /** @return array<string, int> */
    private function counts(string $table, string $column): array
    {
        $stmt = $this->pdo->query('SELECT ' . $column . ' AS k, COUNT(*) AS c FROM ' . $table . ' GROUP BY ' . $column);
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['k']] = (int) $row['c'];
        }
        return $counts;
    }

    private function findModerationAction(string $id): ?OpsModerationAction
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ops_moderation_actions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? OpsModerationAction::fromRow($row) : null;
    }

    private function findEscalation(string $id): ?OpsEscalation
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ops_escalations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? OpsEscalation::fromRow($row) : null;
    }

    private function sourceType(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['manual', 'repair_case', 'provider', 'quote', 'trust_review', 'governance_action', 'learning_event'], true) ? $value : 'manual';
    }

    private function category(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['safety', 'quality', 'content', 'provider_dispute', 'payment_risk', 'policy', 'manual_review'], true) ? $value : 'manual_review';
    }

    private function priority(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['low', 'medium', 'high', 'critical'], true) ? $value : 'medium';
    }

    private function actionType(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['approve', 'suppress', 'require_changes', 'flag_provider', 'refund_review', 'policy_note', 'dismiss'], true) ? $value : 'policy_note';
    }

    private function targetType(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['repair_case', 'provider', 'quote', 'completion_report', 'trust_review', 'governance_action', 'manual'], true) ? $value : 'manual';
    }

    private function escalationLevel(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['ops_lead', 'policy_lead', 'safety_lead', 'founder_review'], true) ? $value : 'ops_lead';
    }

    private function trimmed(mixed $value, int $max, string $fallback): string
    {
        $value = trim((string) $value);
        return $value === '' ? $fallback : substr($value, 0, $max);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
