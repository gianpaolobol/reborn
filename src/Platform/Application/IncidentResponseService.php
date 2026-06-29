<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Throwable;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Support\Uuid;

final class IncidentResponseService
{
    /** @var list<string> */
    private array $alertStatuses = ['open', 'acknowledged', 'resolved'];

    /** @var list<string> */
    private array $incidentStatuses = ['investigating', 'identified', 'monitoring', 'resolved'];

    /** @var list<string> */
    private array $severities = ['low', 'medium', 'high', 'critical'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ProductionReadinessService $readiness,
        private readonly BackupService $backups,
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $statusPage = $this->statusPage();
        $openAlerts = $this->alerts('active', 10);
        $incidents = $this->incidents('active', 10);

        return [
            'status' => $statusPage['status'],
            'generated_at' => gmdate('c'),
            'status_page' => $statusPage,
            'alert_summary' => $this->alertSummary(),
            'incident_summary' => $this->incidentSummary(),
            'active_alerts' => $openAlerts,
            'active_incidents' => $incidents,
            'maintenance' => $this->maintenanceWindows('active', 10),
            'operator_actions' => $this->operatorActions($statusPage, $openAlerts, $incidents),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function alertRules(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, metric, comparator, threshold_value, severity, window_minutes, enabled, description, created_at, updated_at FROM platform_alert_rules ORDER BY severity DESC, name ASC');
        return array_map([$this, 'normalizeRule'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function evaluateAlerts(?string $userId): array
    {
        $created = [];
        $updated = [];
        $cleared = [];
        $evaluations = [];

        foreach ($this->alertRules() as $rule) {
            if (!(bool) ($rule['enabled'] ?? false)) {
                continue;
            }

            $value = $this->metricValue((string) $rule['metric'], (int) $rule['window_minutes']);
            $triggered = $this->compare($value, (string) $rule['comparator'], (float) $rule['threshold_value']);
            $evaluation = [
                'rule_id' => $rule['id'],
                'name' => $rule['name'],
                'metric' => $rule['metric'],
                'value' => $value,
                'threshold' => (float) $rule['threshold_value'],
                'triggered' => $triggered,
            ];
            $evaluations[] = $evaluation;

            $existing = $this->openAlertForRule((string) $rule['id']);
            if ($triggered) {
                $context = [
                    'rule' => $rule,
                    'evaluation' => $evaluation,
                    'evaluated_by' => $userId,
                    'evaluated_at' => gmdate('c'),
                ];

                if ($existing !== null) {
                    $updated[] = $this->refreshAlert($existing, $value, $context);
                    continue;
                }

                $created[] = $this->createAlertFromRule($rule, $value, $context);
                continue;
            }

            if ($existing !== null && $existing['status'] === 'open') {
                $cleared[] = $this->resolveAlert((string) $existing['id'], $userId, 'Auto-resolved after alert rule returned below threshold.');
            }
        }

        return [
            'evaluated_at' => gmdate('c'),
            'evaluations' => $evaluations,
            'created_alerts' => $created,
            'updated_alerts' => $updated,
            'cleared_alerts' => $cleared,
            'summary' => $this->alertSummary(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function alerts(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active') {
            $sql = "SELECT * FROM platform_alerts WHERE status IN ('open', 'acknowledged') ORDER BY opened_at DESC LIMIT :limit";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeAlert'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if (!in_array($status, $this->alertStatuses, true)) {
            $status = 'open';
        }

        $stmt = $this->pdo->prepare('SELECT * FROM platform_alerts WHERE status = :status ORDER BY opened_at DESC LIMIT :limit');
        $stmt->bindValue('status', $status);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAlert'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function acknowledgeAlert(string $alertId, ?string $userId): array
    {
        $alert = $this->requireAlert($alertId);
        if ($alert['status'] === 'resolved') {
            return $alert;
        }

        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_alerts SET status = :status, acknowledged_at = :acknowledged_at, acknowledged_by = :acknowledged_by, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'acknowledged',
            'acknowledged_at' => $now,
            'acknowledged_by' => $userId,
            'updated_at' => $now,
            'id' => $alertId,
        ]);

        return $this->requireAlert($alertId);
    }

    /** @return array<string, mixed> */
    public function resolveAlert(string $alertId, ?string $userId, string $message = 'Resolved by operator.'): array
    {
        $alert = $this->requireAlert($alertId);
        if ($alert['status'] === 'resolved') {
            return $alert;
        }

        $now = gmdate('c');
        $context = $alert['context'];
        $context['resolution_note'] = $message;
        $context['resolved_by'] = $userId;
        $context['resolved_at'] = $now;

        $stmt = $this->pdo->prepare('UPDATE platform_alerts SET status = :status, context_json = :context_json, resolved_at = :resolved_at, resolved_by = :resolved_by, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'resolved',
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'resolved_at' => $now,
            'resolved_by' => $userId,
            'updated_at' => $now,
            'id' => $alertId,
        ]);

        return $this->requireAlert($alertId);
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function createIncident(array $body, ?string $userId): array
    {
        $title = trim((string) ($body['title'] ?? ''));
        $severity = strtolower(trim((string) ($body['severity'] ?? 'medium')));
        $summary = trim((string) ($body['summary'] ?? ''));
        $impact = trim((string) ($body['impact'] ?? ''));
        $linkedAlertId = trim((string) ($body['linked_alert_id'] ?? ''));

        $errors = [];
        if ($title === '') {
            $errors['title'][] = 'title is required.';
        }
        if ($summary === '') {
            $errors['summary'][] = 'summary is required.';
        }
        if (!in_array($severity, $this->severities, true)) {
            $errors['severity'][] = 'severity must be low, medium, high or critical.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($linkedAlertId !== '') {
            $this->requireAlert($linkedAlertId);
        }

        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_incidents (id, title, severity, status, summary, impact, linked_alert_id, opened_by, assigned_to, opened_at, updated_at, resolved_at) VALUES (:id, :title, :severity, :status, :summary, :impact, :linked_alert_id, :opened_by, :assigned_to, :opened_at, :updated_at, NULL)');
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'severity' => $severity,
            'status' => 'investigating',
            'summary' => $summary,
            'impact' => $impact !== '' ? $impact : null,
            'linked_alert_id' => $linkedAlertId !== '' ? $linkedAlertId : null,
            'opened_by' => $userId,
            'assigned_to' => $body['assigned_to'] ?? null,
            'opened_at' => $now,
            'updated_at' => $now,
        ]);

        $this->createStatusUpdate([
            'incident_id' => $id,
            'component' => 'platform',
            'status' => 'investigating',
            'message' => $summary,
        ], $userId);

        return $this->requireIncident($id);
    }

    /** @return list<array<string, mixed>> */
    public function incidents(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("SELECT * FROM platform_incidents WHERE status != 'resolved' ORDER BY opened_at DESC LIMIT :limit");
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeIncident'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if (!in_array($status, $this->incidentStatuses, true)) {
            $status = 'investigating';
        }

        $stmt = $this->pdo->prepare('SELECT * FROM platform_incidents WHERE status = :status ORDER BY opened_at DESC LIMIT :limit');
        $stmt->bindValue('status', $status);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeIncident'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function updateIncidentStatus(string $incidentId, array $body, ?string $userId): array
    {
        $incident = $this->requireIncident($incidentId);
        $status = strtolower(trim((string) ($body['status'] ?? '')));
        $message = trim((string) ($body['message'] ?? ''));

        $errors = [];
        if (!in_array($status, $this->incidentStatuses, true)) {
            $errors['status'][] = 'status must be investigating, identified, monitoring or resolved.';
        }
        if ($message === '') {
            $errors['message'][] = 'message is required.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_incidents SET status = :status, updated_at = :updated_at, resolved_at = :resolved_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'updated_at' => $now,
            'resolved_at' => $status === 'resolved' ? $now : ($incident['resolved_at'] ?? null),
            'id' => $incidentId,
        ]);

        $this->createStatusUpdate([
            'incident_id' => $incidentId,
            'component' => (string) ($body['component'] ?? 'platform'),
            'status' => $status,
            'message' => $message,
        ], $userId);

        return $this->requireIncident($incidentId);
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function createStatusUpdate(array $body, ?string $userId): array
    {
        $component = trim((string) ($body['component'] ?? 'platform'));
        $status = trim((string) ($body['status'] ?? 'operational'));
        $message = trim((string) ($body['message'] ?? ''));
        $incidentId = trim((string) ($body['incident_id'] ?? ''));

        $errors = [];
        if ($component === '') {
            $errors['component'][] = 'component is required.';
        }
        if ($message === '') {
            $errors['message'][] = 'message is required.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        if ($incidentId !== '') {
            $this->requireIncident($incidentId);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_status_updates (id, incident_id, component, status, message, created_by, created_at) VALUES (:id, :incident_id, :component, :status, :message, :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'incident_id' => $incidentId !== '' ? $incidentId : null,
            'component' => $component,
            'status' => $status,
            'message' => $message,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        return $this->requireStatusUpdate($id);
    }

    /** @return array<string, mixed> */
    public function statusPage(): array
    {
        $readiness = $this->readiness->readiness();
        $activeIncidents = $this->incidents('active', 20);
        $activeAlerts = $this->alerts('active', 20);
        $maintenance = $this->maintenanceWindows('active', 10);
        $updates = $this->statusUpdates(10);

        $status = 'operational';
        if ($maintenance !== []) {
            $status = 'maintenance';
        }
        if (($readiness['status'] ?? 'unknown') === 'degraded' || $activeAlerts !== []) {
            $status = $status === 'operational' ? 'degraded' : $status;
        }
        if (($readiness['status'] ?? 'unknown') === 'not_ready' || $this->hasCriticalIncident($activeIncidents) || $this->hasCriticalAlert($activeAlerts)) {
            $status = 'major_outage';
        }
        if ($activeIncidents !== [] && $status === 'operational') {
            $status = 'partial_outage';
        }

        return [
            'status_page_version' => 'status_page_v1_step22',
            'status' => $status,
            'generated_at' => gmdate('c'),
            'readiness_status' => $readiness['status'] ?? 'unknown',
            'components' => [
                ['name' => 'API', 'status' => $this->componentStatus($readiness['status'] ?? 'unknown')],
                ['name' => 'Database', 'status' => $this->componentStatus((string) ($readiness['checks']['database']['status'] ?? 'unknown'))],
                ['name' => 'Storage', 'status' => $this->componentStatus((string) ($readiness['checks']['storage']['status'] ?? 'unknown'))],
                ['name' => 'Backups', 'status' => $this->backupComponentStatus()],
                ['name' => 'Prototype', 'status' => 'operational'],
            ],
            'active_incidents' => $activeIncidents,
            'active_alerts' => $activeAlerts,
            'maintenance_windows' => $maintenance,
            'recent_updates' => $updates,
            'public_note' => 'This MVP status payload is local/pilot oriented and does not replace external uptime monitoring.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function statusUpdates(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare('SELECT id, incident_id, component, status, message, created_by, created_at FROM platform_status_updates ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function createMaintenanceWindow(array $body, ?string $userId): array
    {
        $title = trim((string) ($body['title'] ?? ''));
        $reason = trim((string) ($body['reason'] ?? ''));
        $startsAt = trim((string) ($body['starts_at'] ?? gmdate('c')));
        $endsAt = trim((string) ($body['ends_at'] ?? gmdate('c', time() + 3600)));
        $status = strtolower(trim((string) ($body['status'] ?? 'scheduled')));

        $errors = [];
        if ($title === '') {
            $errors['title'][] = 'title is required.';
        }
        if (!in_array($status, ['scheduled', 'active', 'completed', 'cancelled'], true)) {
            $errors['status'][] = 'status must be scheduled, active, completed or cancelled.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_maintenance_windows (id, title, status, starts_at, ends_at, reason, created_by, created_at, updated_at) VALUES (:id, :title, :status, :starts_at, :ends_at, :reason, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $reason !== '' ? $reason : null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->createStatusUpdate([
            'component' => 'platform',
            'status' => 'maintenance_scheduled',
            'message' => 'Maintenance scheduled: ' . $title,
        ], $userId);

        return $this->requireMaintenanceWindow($id);
    }

    /** @return list<array<string, mixed>> */
    public function maintenanceWindows(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $now = gmdate('c');
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("SELECT * FROM platform_maintenance_windows WHERE status IN ('scheduled', 'active') AND ends_at >= :now ORDER BY starts_at ASC LIMIT :limit");
            $stmt->bindValue('now', $now);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM platform_maintenance_windows ORDER BY starts_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function closeMaintenanceWindow(string $id, ?string $userId): array
    {
        $window = $this->requireMaintenanceWindow($id);
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_maintenance_windows SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'completed',
            'updated_at' => $now,
            'id' => $id,
        ]);
        $this->createStatusUpdate([
            'component' => 'platform',
            'status' => 'maintenance_completed',
            'message' => 'Maintenance completed: ' . $window['title'],
        ], $userId);

        return $this->requireMaintenanceWindow($id);
    }

    /** @return array<string, int> */
    private function alertSummary(): array
    {
        $summary = ['open' => 0, 'acknowledged' => 0, 'resolved' => 0];
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM platform_alerts GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }
        return $summary;
    }

    /** @return array<string, int> */
    private function incidentSummary(): array
    {
        $summary = ['investigating' => 0, 'identified' => 0, 'monitoring' => 0, 'resolved' => 0];
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM platform_incidents GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }
        return $summary;
    }

    private function metricValue(string $metric, int $windowMinutes): float
    {
        $cutoff = gmdate('c', time() - max(1, $windowMinutes) * 60);
        try {
            return match ($metric) {
                'http_5xx_count' => $this->metricCount('status_code >= 500', $cutoff),
                'http_4xx_count' => $this->metricCount('status_code >= 400 AND status_code < 500', $cutoff),
                'http_avg_duration_ms' => $this->metricAverageDuration($cutoff),
                'readiness_not_ready' => ($this->readiness->readiness()['status'] ?? 'not_ready') === 'not_ready' ? 1.0 : 0.0,
                'backup_age_hours' => $this->backupAgeHours(),
                default => 0.0,
            };
        } catch (Throwable) {
            return $metric === 'readiness_not_ready' ? 1.0 : 0.0;
        }
    }

    private function metricCount(string $where, string $cutoff): float
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM platform_http_metrics WHERE {$where} AND occurred_at >= :cutoff");
        $stmt->execute(['cutoff' => $cutoff]);
        return (float) $stmt->fetchColumn();
    }

    private function metricAverageDuration(string $cutoff): float
    {
        $stmt = $this->pdo->prepare('SELECT AVG(duration_ms) FROM platform_http_metrics WHERE occurred_at >= :cutoff');
        $stmt->execute(['cutoff' => $cutoff]);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private function backupAgeHours(): float
    {
        $status = $this->backups->status();
        $latest = $status['latest_backup']['created_at'] ?? null;
        if (!is_string($latest) || $latest === '') {
            return 9999.0;
        }

        $timestamp = strtotime($latest);
        if ($timestamp === false) {
            return 9999.0;
        }

        return max(0.0, (time() - $timestamp) / 3600);
    }

    private function compare(float $value, string $comparator, float $threshold): bool
    {
        return match ($comparator) {
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            '==' => abs($value - $threshold) < 0.0001,
            '!=' => abs($value - $threshold) >= 0.0001,
            default => false,
        };
    }

    /** @return array<string, mixed>|null */
    private function openAlertForRule(string $ruleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_alerts WHERE rule_id = :rule_id AND status IN ('open', 'acknowledged') ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute(['rule_id' => $ruleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeAlert($row) : null;
    }

    /** @param array<string, mixed> $rule @param array<string, mixed> $context @return array<string, mixed> */
    private function createAlertFromRule(array $rule, float $value, array $context): array
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $message = sprintf('%s: %s is %.2f %s %.2f.', (string) $rule['severity'], (string) $rule['metric'], $value, (string) $rule['comparator'], (float) $rule['threshold_value']);
        $stmt = $this->pdo->prepare('INSERT INTO platform_alerts (id, rule_id, name, severity, status, metric, metric_value, threshold_value, message, context_json, opened_at, acknowledged_at, acknowledged_by, resolved_at, resolved_by, created_at, updated_at) VALUES (:id, :rule_id, :name, :severity, :status, :metric, :metric_value, :threshold_value, :message, :context_json, :opened_at, NULL, NULL, NULL, NULL, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'rule_id' => $rule['id'],
            'name' => $rule['name'],
            'severity' => $rule['severity'],
            'status' => 'open',
            'metric' => $rule['metric'],
            'metric_value' => $value,
            'threshold_value' => (float) $rule['threshold_value'],
            'message' => $message,
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'opened_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->requireAlert($id);
    }

    /** @param array<string, mixed> $alert @param array<string, mixed> $context @return array<string, mixed> */
    private function refreshAlert(array $alert, float $value, array $context): array
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_alerts SET metric_value = :metric_value, context_json = :context_json, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'metric_value' => $value,
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'id' => $alert['id'],
        ]);

        return $this->requireAlert((string) $alert['id']);
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
        return $this->normalizeIncident($row);
    }

    /** @return array<string, mixed> */
    private function requireStatusUpdate(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_status_updates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Status update not found.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireMaintenanceWindow(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_maintenance_windows WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Maintenance window not found.');
        }
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRule(array $row): array
    {
        $row['threshold_value'] = (float) $row['threshold_value'];
        $row['window_minutes'] = (int) $row['window_minutes'];
        $row['enabled'] = (bool) $row['enabled'];
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeAlert(array $row): array
    {
        $row['metric_value'] = (float) $row['metric_value'];
        $row['threshold_value'] = (float) $row['threshold_value'];
        $row['context'] = json_decode((string) ($row['context_json'] ?? '{}'), true) ?: [];
        unset($row['context_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeIncident(array $row): array
    {
        $stmt = $this->pdo->prepare('SELECT id, incident_id, component, status, message, created_by, created_at FROM platform_status_updates WHERE incident_id = :incident_id ORDER BY created_at DESC LIMIT 5');
        $stmt->execute(['incident_id' => $row['id']]);
        $row['updates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    /** @param list<array<string, mixed>> $incidents */
    private function hasCriticalIncident(array $incidents): bool
    {
        foreach ($incidents as $incident) {
            if (($incident['severity'] ?? '') === 'critical') {
                return true;
            }
        }
        return false;
    }

    /** @param list<array<string, mixed>> $alerts */
    private function hasCriticalAlert(array $alerts): bool
    {
        foreach ($alerts as $alert) {
            if (in_array(($alert['severity'] ?? ''), ['critical', 'high'], true)) {
                return true;
            }
        }
        return false;
    }

    private function componentStatus(string $status): string
    {
        return match ($status) {
            'ready', 'ok' => 'operational',
            'degraded', 'warn' => 'degraded',
            'not_ready', 'fail' => 'major_outage',
            default => 'unknown',
        };
    }

    private function backupComponentStatus(): string
    {
        $age = $this->backupAgeHours();
        if ($age > 999) {
            return 'degraded';
        }
        return $age > 24 ? 'degraded' : 'operational';
    }

    /** @param array<string, mixed> $statusPage @param list<array<string, mixed>> $alerts @param list<array<string, mixed>> $incidents @return list<string> */
    private function operatorActions(array $statusPage, array $alerts, array $incidents): array
    {
        $actions = [];
        if ($alerts !== []) {
            $actions[] = 'Evaluate, acknowledge or resolve active alerts before pilot/demo use.';
        }
        if ($incidents !== []) {
            $actions[] = 'Post a status update for every active incident until it is resolved.';
        }
        if (($statusPage['status'] ?? 'operational') !== 'operational') {
            $actions[] = 'Keep the public status page aligned with current readiness and incident impact.';
        }
        if ($actions === []) {
            $actions[] = 'Run alert evaluation after every smoke-test pass and before a demo handoff.';
        }
        return $actions;
    }
}
