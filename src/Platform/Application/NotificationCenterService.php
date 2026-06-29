<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class NotificationCenterService
{
    /** @var list<string> */
    private array $channelTypes = ['in_app', 'email', 'webhook', 'sms', 'slack'];

    /** @var list<string> */
    private array $channelStatuses = ['active', 'paused', 'disabled'];

    /** @var list<string> */
    private array $deliveryStatuses = ['queued', 'sent', 'failed', 'cancelled'];

    /** @var list<string> */
    private array $severities = ['low', 'medium', 'high', 'critical'];

    /** @var array<string, int> */
    private array $severityRank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $pending = $this->deliveries('queued', 20);
        $runs = $this->escalationRuns('active', 10);

        return [
            'notification_center_version' => 'notification_center_v1_step23',
            'generated_at' => gmdate('c'),
            'delivery_summary' => $this->deliverySummary(),
            'escalation_summary' => $this->escalationSummary(),
            'channels' => $this->channels(),
            'rules' => $this->notificationRules(),
            'pending_deliveries' => $pending,
            'recent_deliveries' => $this->deliveries('all', 20),
            'escalation_policies' => $this->escalationPolicies(),
            'active_escalations' => $runs,
            'operator_actions' => $this->operatorActions($pending, $runs),
            'transport_note' => 'Step 23 uses local/mock transports only. External email, SMS, Slack or webhook sending must be explicitly integrated later.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function channels(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, type, target, status, config_json, last_used_at, created_by, created_at, updated_at FROM platform_notification_channels ORDER BY status ASC, name ASC');
        return array_map([$this, 'normalizeChannel'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function createChannel(array $body, ?string $userId): array
    {
        $name = trim((string) ($body['name'] ?? ''));
        $type = strtolower(trim((string) ($body['type'] ?? 'in_app')));
        $target = trim((string) ($body['target'] ?? ''));
        $status = strtolower(trim((string) ($body['status'] ?? 'active')));
        $config = is_array($body['config'] ?? null) ? $body['config'] : [];

        $errors = [];
        if ($name === '') {
            $errors['name'][] = 'name is required.';
        }
        if (!in_array($type, $this->channelTypes, true)) {
            $errors['type'][] = 'type must be in_app, email, webhook, sms or slack.';
        }
        if ($target === '') {
            $errors['target'][] = 'target is required.';
        }
        if (!in_array($status, $this->channelStatuses, true)) {
            $errors['status'][] = 'status must be active, paused or disabled.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_notification_channels (id, name, type, target, status, config_json, last_used_at, created_by, created_at, updated_at) VALUES (:id, :name, :type, :target, :status, :config_json, NULL, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'target' => $target,
            'status' => $status,
            'config_json' => json_encode(['mock' => true] + $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->requireChannel($id);
    }

    /** @return list<array<string, mixed>> */
    public function notificationRules(): array
    {
        $stmt = $this->pdo->query('SELECT r.id, r.name, r.trigger_type, r.min_severity, r.channel_id, r.enabled, r.template_subject, r.template_body, r.created_at, r.updated_at, c.name AS channel_name, c.type AS channel_type, c.target AS channel_target FROM platform_notification_rules r JOIN platform_notification_channels c ON c.id = r.channel_id ORDER BY r.trigger_type ASC, r.min_severity DESC, r.name ASC');
        return array_map([$this, 'normalizeRule'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function deliveries(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status !== 'all' && in_array($status, $this->deliveryStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT d.*, c.name AS channel_name, c.type AS channel_type, c.target AS channel_target FROM platform_notification_deliveries d JOIN platform_notification_channels c ON c.id = d.channel_id WHERE d.status = :status ORDER BY d.dispatched_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeDelivery'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT d.*, c.name AS channel_name, c.type AS channel_type, c.target AS channel_target FROM platform_notification_deliveries d JOIN platform_notification_channels c ON c.id = d.channel_id ORDER BY d.dispatched_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeDelivery'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function dispatch(array $body, ?string $userId): array
    {
        $targetType = strtolower(trim((string) ($body['target_type'] ?? 'active_operations')));
        $targetId = trim((string) ($body['target_id'] ?? ''));
        $created = [];

        if ($targetType === 'manual') {
            $created[] = $this->createManualDelivery($body, $userId);
        } elseif ($targetType === 'alert' && $targetId !== '') {
            $created = array_merge($created, $this->dispatchAlert($this->requireAlert($targetId), $userId));
        } elseif ($targetType === 'incident' && $targetId !== '') {
            $created = array_merge($created, $this->dispatchIncident($this->requireIncident($targetId), $userId));
        } else {
            foreach ($this->activeAlerts(20) as $alert) {
                $created = array_merge($created, $this->dispatchAlert($alert, $userId));
            }
            foreach ($this->activeIncidents(20) as $incident) {
                $created = array_merge($created, $this->dispatchIncident($incident, $userId));
            }
            if ($created === []) {
                $created[] = $this->createManualDelivery([
                    'subject' => 'Re-born operational heartbeat',
                    'message' => 'No active alerts or incidents. Notification center dispatch verified.',
                    'severity' => 'low',
                    'target_type' => 'heartbeat',
                ], $userId);
            }
        }

        return [
            'dispatched_at' => gmdate('c'),
            'created_count' => count($created),
            'deliveries' => $created,
            'summary' => $this->deliverySummary(),
        ];
    }

    /** @return array<string, mixed> */
    public function markDelivery(string $id, string $status, ?string $message = null): array
    {
        if (!in_array($status, $this->deliveryStatuses, true)) {
            throw new ValidationException(['status' => ['status must be queued, sent, failed or cancelled.']]);
        }

        $this->requireDelivery($id);
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_notification_deliveries SET status = :status, error_message = :error_message, sent_at = :sent_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'error_message' => $status === 'failed' ? ($message ?: 'Marked failed by operator.') : null,
            'sent_at' => $status === 'sent' ? $now : null,
            'updated_at' => $now,
            'id' => $id,
        ]);

        return $this->requireDelivery($id);
    }

    /** @return list<array<string, mixed>> */
    public function escalationPolicies(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, severity, enabled, steps_json, created_at, updated_at FROM platform_escalation_policies ORDER BY enabled DESC, severity DESC, name ASC');
        return array_map([$this, 'normalizePolicy'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function escalationRuns(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("SELECT er.*, ep.name AS policy_name, i.title AS incident_title, i.severity AS incident_severity FROM platform_escalation_runs er JOIN platform_escalation_policies ep ON ep.id = er.policy_id JOIN platform_incidents i ON i.id = er.incident_id WHERE er.status IN ('running', 'waiting') ORDER BY er.created_at DESC LIMIT :limit");
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeRun'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT er.*, ep.name AS policy_name, i.title AS incident_title, i.severity AS incident_severity FROM platform_escalation_runs er JOIN platform_escalation_policies ep ON ep.id = er.policy_id JOIN platform_incidents i ON i.id = er.incident_id ORDER BY er.created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeRun'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function escalateIncident(string $incidentId, array $body, ?string $userId): array
    {
        $incident = $this->requireIncident($incidentId);
        if (($incident['status'] ?? '') === 'resolved') {
            throw new ValidationException(['incident' => ['resolved incidents cannot be escalated.']]);
        }

        $policyId = trim((string) ($body['policy_id'] ?? ''));
        $policy = $policyId !== '' ? $this->requirePolicy($policyId) : $this->policyForSeverity((string) $incident['severity']);

        $now = gmdate('c');
        $id = Uuid::v4();
        $context = [
            'incident' => [
                'id' => $incident['id'],
                'title' => $incident['title'],
                'severity' => $incident['severity'],
                'status' => $incident['status'],
            ],
            'policy' => [
                'id' => $policy['id'],
                'name' => $policy['name'],
                'steps' => $policy['steps'],
            ],
            'note' => trim((string) ($body['note'] ?? 'Escalated from Step 23 notification center.')),
        ];

        $stmt = $this->pdo->prepare('INSERT INTO platform_escalation_runs (id, policy_id, incident_id, status, current_step, summary, context_json, created_by, created_at, updated_at, completed_at) VALUES (:id, :policy_id, :incident_id, :status, :current_step, :summary, :context_json, :created_by, :created_at, :updated_at, NULL)');
        $stmt->execute([
            'id' => $id,
            'policy_id' => $policy['id'],
            'incident_id' => $incidentId,
            'status' => 'running',
            'current_step' => 1,
            'summary' => sprintf('Escalation started for %s incident: %s', (string) $incident['severity'], (string) $incident['title']),
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $deliveries = $this->dispatchIncident($incident, $userId, $id);

        return [
            'escalation_run' => $this->requireRun($id),
            'notification_deliveries' => $deliveries,
        ];
    }

    /** @return array<string, int> */
    private function deliverySummary(): array
    {
        $summary = ['queued' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM platform_notification_deliveries GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }
        return $summary;
    }

    /** @return array<string, int> */
    private function escalationSummary(): array
    {
        $summary = ['running' => 0, 'waiting' => 0, 'completed' => 0, 'cancelled' => 0];
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM platform_escalation_runs GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }
        return $summary;
    }

    /** @return list<array<string, mixed>> */
    private function activeAlerts(int $limit): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_alerts WHERE status IN ('open', 'acknowledged') ORDER BY opened_at DESC LIMIT :limit");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAlert'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    private function activeIncidents(int $limit): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_incidents WHERE status != 'resolved' ORDER BY opened_at DESC LIMIT :limit");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $alert @return list<array<string, mixed>> */
    private function dispatchAlert(array $alert, ?string $userId): array
    {
        $created = [];
        foreach ($this->rulesFor('alert', (string) $alert['severity']) as $rule) {
            $created[] = $this->createDelivery($rule, [
                'target_type' => 'alert',
                'target_id' => $alert['id'],
                'severity' => $alert['severity'],
                'title' => $alert['name'],
                'summary' => $alert['message'],
                'payload' => ['alert' => $alert],
            ], $userId);
        }
        return $created;
    }

    /** @param array<string, mixed> $incident @return list<array<string, mixed>> */
    private function dispatchIncident(array $incident, ?string $userId, ?string $escalationRunId = null): array
    {
        $created = [];
        foreach ($this->rulesFor('incident', (string) $incident['severity']) as $rule) {
            $created[] = $this->createDelivery($rule, [
                'target_type' => $escalationRunId ? 'escalation_run' : 'incident',
                'target_id' => $escalationRunId ?: $incident['id'],
                'severity' => $incident['severity'],
                'title' => $incident['title'],
                'summary' => $incident['summary'],
                'payload' => ['incident' => $incident, 'escalation_run_id' => $escalationRunId],
            ], $userId);
        }
        return $created;
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private function createManualDelivery(array $body, ?string $userId): array
    {
        $channel = $this->primaryChannel();
        $severity = strtolower(trim((string) ($body['severity'] ?? 'low')));
        if (!in_array($severity, $this->severities, true)) {
            $severity = 'low';
        }
        $targetType = trim((string) ($body['target_type'] ?? 'manual')) ?: 'manual';
        return $this->insertDelivery(
            $channel,
            null,
            $targetType,
            trim((string) ($body['target_id'] ?? '')) ?: null,
            $severity,
            trim((string) ($body['subject'] ?? 'Re-born operator notification')),
            trim((string) ($body['message'] ?? 'Manual notification created from Step 23.')),
            ['manual' => true, 'payload' => $body],
            $userId
        );
    }

    /** @param array<string, mixed> $rule @param array<string, mixed> $target @return array<string, mixed> */
    private function createDelivery(array $rule, array $target, ?string $userId): array
    {
        $channel = $this->requireChannel((string) $rule['channel_id']);
        $subject = $this->renderTemplate((string) $rule['template_subject'], $target);
        $message = $this->renderTemplate((string) $rule['template_body'], $target);
        return $this->insertDelivery(
            $channel,
            (string) $rule['id'],
            (string) $target['target_type'],
            isset($target['target_id']) ? (string) $target['target_id'] : null,
            (string) $target['severity'],
            $subject,
            $message,
            $target['payload'] ?? [],
            $userId
        );
    }

    /** @param array<string, mixed> $channel @param array<string, mixed> $payload @return array<string, mixed> */
    private function insertDelivery(array $channel, ?string $ruleId, string $targetType, ?string $targetId, string $severity, string $subject, string $message, array $payload, ?string $userId): array
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $transport = (string) $channel['type'];
        $payload = [
            'mock_transport' => true,
            'channel' => ['id' => $channel['id'], 'name' => $channel['name'], 'type' => $channel['type'], 'target' => $channel['target']],
            'body' => $payload,
        ];

        $stmt = $this->pdo->prepare('INSERT INTO platform_notification_deliveries (id, channel_id, rule_id, target_type, target_id, severity, subject, message, status, transport, payload_json, error_message, dispatched_by, dispatched_at, sent_at, created_at, updated_at) VALUES (:id, :channel_id, :rule_id, :target_type, :target_id, :severity, :subject, :message, :status, :transport, :payload_json, NULL, :dispatched_by, :dispatched_at, NULL, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'channel_id' => $channel['id'],
            'rule_id' => $ruleId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'severity' => in_array($severity, $this->severities, true) ? $severity : 'low',
            'subject' => $subject !== '' ? $subject : 'Re-born notification',
            'message' => $message !== '' ? $message : 'Notification created.',
            'status' => 'queued',
            'transport' => $transport,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'dispatched_by' => $userId,
            'dispatched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->pdo->prepare('UPDATE platform_notification_channels SET last_used_at = :last_used_at, updated_at = :updated_at WHERE id = :id')->execute([
            'last_used_at' => $now,
            'updated_at' => $now,
            'id' => $channel['id'],
        ]);

        return $this->requireDelivery($id);
    }

    /** @return list<array<string, mixed>> */
    private function rulesFor(string $triggerType, string $severity): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, c.status AS channel_status FROM platform_notification_rules r JOIN platform_notification_channels c ON c.id = r.channel_id WHERE r.trigger_type = :trigger_type AND r.enabled = 1 AND c.status = :status ORDER BY r.min_severity DESC, r.name ASC');
        $stmt->execute(['trigger_type' => $triggerType, 'status' => 'active']);
        $rules = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $min = (string) $row['min_severity'];
            if (($this->severityRank[$severity] ?? 0) >= ($this->severityRank[$min] ?? 1)) {
                $rules[] = $this->normalizeRule($row);
            }
        }
        return $rules;
    }

    /** @param array<string, mixed> $target */
    private function renderTemplate(string $template, array $target): string
    {
        $replacements = [
            '{{severity}}' => (string) ($target['severity'] ?? 'low'),
            '{{title}}' => (string) ($target['title'] ?? 'Untitled'),
            '{{summary}}' => (string) ($target['summary'] ?? ''),
            '{{target_type}}' => (string) ($target['target_type'] ?? ''),
            '{{target_id}}' => (string) ($target['target_id'] ?? ''),
        ];
        return strtr($template, $replacements);
    }

    /** @return array<string, mixed> */
    private function primaryChannel(): array
    {
        $stmt = $this->pdo->query("SELECT id FROM platform_notification_channels WHERE status = 'active' ORDER BY CASE type WHEN 'in_app' THEN 0 ELSE 1 END, name ASC LIMIT 1");
        $id = $stmt->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new NotFoundException('No active notification channel is available.');
        }
        return $this->requireChannel($id);
    }

    /** @return array<string, mixed> */
    private function requireChannel(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, type, target, status, config_json, last_used_at, created_by, created_at, updated_at FROM platform_notification_channels WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Notification channel not found.');
        }
        return $this->normalizeChannel($row);
    }

    /** @return array<string, mixed> */
    private function requireDelivery(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT d.*, c.name AS channel_name, c.type AS channel_type, c.target AS channel_target FROM platform_notification_deliveries d JOIN platform_notification_channels c ON c.id = d.channel_id WHERE d.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Notification delivery not found.');
        }
        return $this->normalizeDelivery($row);
    }

    /** @return array<string, mixed> */
    private function requireAlert(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_alerts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Alert not found.');
        }
        return $this->normalizeAlert($row);
    }

    /** @return array<string, mixed> */
    private function requireIncident(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_incidents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Incident not found.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requirePolicy(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, severity, enabled, steps_json, created_at, updated_at FROM platform_escalation_policies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Escalation policy not found.');
        }
        return $this->normalizePolicy($row);
    }

    /** @return array<string, mixed> */
    private function policyForSeverity(string $severity): array
    {
        $rank = $this->severityRank[$severity] ?? 2;
        $candidate = null;
        foreach ($this->escalationPolicies() as $policy) {
            if (!(bool) ($policy['enabled'] ?? false)) {
                continue;
            }
            $policyRank = $this->severityRank[(string) $policy['severity']] ?? 1;
            if ($policyRank <= $rank && ($candidate === null || $policyRank > ($this->severityRank[(string) $candidate['severity']] ?? 0))) {
                $candidate = $policy;
            }
        }
        if ($candidate === null) {
            $policies = array_values(array_filter($this->escalationPolicies(), static fn (array $policy): bool => (bool) ($policy['enabled'] ?? false)));
            if ($policies === []) {
                throw new NotFoundException('No enabled escalation policy is available for this severity.');
            }
            $candidate = $policies[count($policies) - 1];
        }
        return $candidate;
    }

    /** @return array<string, mixed> */
    private function requireRun(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT er.*, ep.name AS policy_name, i.title AS incident_title, i.severity AS incident_severity FROM platform_escalation_runs er JOIN platform_escalation_policies ep ON ep.id = er.policy_id JOIN platform_incidents i ON i.id = er.incident_id WHERE er.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Escalation run not found.');
        }
        return $this->normalizeRun($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeChannel(array $row): array
    {
        $row['config'] = json_decode((string) ($row['config_json'] ?? '{}'), true) ?: [];
        unset($row['config_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRule(array $row): array
    {
        $row['enabled'] = (bool) ($row['enabled'] ?? false);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeDelivery(array $row): array
    {
        $row['payload'] = json_decode((string) ($row['payload_json'] ?? '{}'), true) ?: [];
        unset($row['payload_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeAlert(array $row): array
    {
        $row['metric_value'] = (float) ($row['metric_value'] ?? 0);
        $row['threshold_value'] = (float) ($row['threshold_value'] ?? 0);
        $row['context'] = json_decode((string) ($row['context_json'] ?? '{}'), true) ?: [];
        unset($row['context_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePolicy(array $row): array
    {
        $row['enabled'] = (bool) ($row['enabled'] ?? false);
        $row['steps'] = json_decode((string) ($row['steps_json'] ?? '[]'), true) ?: [];
        unset($row['steps_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRun(array $row): array
    {
        $row['current_step'] = (int) ($row['current_step'] ?? 1);
        $row['context'] = json_decode((string) ($row['context_json'] ?? '{}'), true) ?: [];
        unset($row['context_json']);
        return $row;
    }

    /** @param list<array<string, mixed>> $pending @param list<array<string, mixed>> $runs @return list<string> */
    private function operatorActions(array $pending, array $runs): array
    {
        $actions = [];
        if ($pending !== []) {
            $actions[] = 'Review queued notifications and mark mock deliveries as sent or failed after operator action.';
        }
        if ($runs !== []) {
            $actions[] = 'Track active escalation runs until every incident has a status update and owner.';
        }
        if ($actions === []) {
            $actions[] = 'Run notification dispatch after alert evaluation and before pilot/demo handoff.';
        }
        return $actions;
    }
}
