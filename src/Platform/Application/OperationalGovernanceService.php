<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class OperationalGovernanceService
{
    /** @var list<string> */
    private array $sourceTypes = ['alert', 'incident'];

    /** @var list<string> */
    private array $severities = ['low', 'medium', 'high', 'critical'];

    /** @var list<string> */
    private array $evaluationStatuses = ['within_sla', 'at_risk', 'breached', 'met'];

    /** @return array<string, mixed> */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $evaluations = $this->slaEvaluations('active', 25);
        $policies = $this->operationalPolicies();
        $breaches = array_values(array_filter($evaluations, static fn (array $row): bool => $row['status'] === 'breached'));
        $atRisk = array_values(array_filter($evaluations, static fn (array $row): bool => $row['status'] === 'at_risk'));

        return [
            'service_governance_version' => 'service_level_operational_governance_v1_step24',
            'generated_at' => gmdate('c'),
            'sla_summary' => $this->slaSummary(),
            'policy_summary' => $this->policySummary(),
            'active_sla_evaluations' => $evaluations,
            'sla_breaches' => $breaches,
            'sla_at_risk' => $atRisk,
            'sla_policies' => $this->slaPolicies(),
            'operational_policies' => $policies,
            'recent_attestations' => $this->policyAttestations(15),
            'operator_actions' => $this->operatorActions($breaches, $atRisk, $policies),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function slaPolicies(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, source_type, severity, response_minutes, resolution_minutes, enabled, description, created_at, updated_at FROM platform_sla_policies ORDER BY source_type ASC, severity DESC, response_minutes ASC');
        return array_map([$this, 'normalizeSlaPolicy'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function evaluateSlas(?string $userId): array
    {
        $sources = array_merge($this->activeAlerts(), $this->activeIncidents());
        $created = [];
        $updated = [];
        $evaluated = [];

        foreach ($sources as $source) {
            $policy = $this->policyFor((string) $source['source_type'], (string) $source['severity']);
            if ($policy === null) {
                continue;
            }

            $evaluation = $this->evaluateSource($source, $policy, $userId);
            $evaluated[] = $evaluation;
            if (($evaluation['was_created'] ?? false) === true) {
                $created[] = $evaluation;
            } else {
                $updated[] = $evaluation;
            }
        }

        return [
            'evaluated_at' => gmdate('c'),
            'evaluated_count' => count($evaluated),
            'created_count' => count($created),
            'updated_count' => count($updated),
            'created' => $created,
            'updated' => $updated,
            'summary' => $this->slaSummary(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function slaEvaluations(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("SELECT e.*, p.name AS policy_name, p.source_type AS policy_source_type FROM platform_sla_evaluations e JOIN platform_sla_policies p ON p.id = e.policy_id WHERE e.status IN ('within_sla', 'at_risk', 'breached') ORDER BY CASE e.status WHEN 'breached' THEN 1 WHEN 'at_risk' THEN 2 ELSE 3 END, e.resolution_due_at ASC LIMIT :limit");
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeEvaluation'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if (!in_array($status, $this->evaluationStatuses, true)) {
            $status = 'within_sla';
        }

        $stmt = $this->pdo->prepare('SELECT e.*, p.name AS policy_name, p.source_type AS policy_source_type FROM platform_sla_evaluations e JOIN platform_sla_policies p ON p.id = e.policy_id WHERE e.status = :status ORDER BY e.evaluated_at DESC LIMIT :limit');
        $stmt->bindValue('status', $status);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeEvaluation'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function markSlaResponse(string $evaluationId, ?string $userId, string $note = 'First response recorded by operator.'): array
    {
        $evaluation = $this->requireEvaluation($evaluationId);
        $now = gmdate('c');
        $context = $evaluation['context'];
        $context['manual_response_note'] = $note;
        $context['manual_response_by'] = $userId;
        $context['manual_response_at'] = $now;

        $status = $this->statusFromDates($now, $evaluation['resolved_at'], $evaluation['response_due_at'], $evaluation['resolution_due_at']);
        $stmt = $this->pdo->prepare('UPDATE platform_sla_evaluations SET first_response_at = COALESCE(first_response_at, :first_response_at), response_breached = :response_breached, status = :status, context_json = :context_json, evaluated_by = :evaluated_by, evaluated_at = :evaluated_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'first_response_at' => $now,
            'response_breached' => strtotime($now) > strtotime((string) $evaluation['response_due_at']) ? 1 : 0,
            'status' => $status,
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'evaluated_by' => $userId,
            'evaluated_at' => $now,
            'updated_at' => $now,
            'id' => $evaluationId,
        ]);

        return $this->requireEvaluation($evaluationId);
    }

    /** @return array<string, mixed> */
    public function markSlaResolved(string $evaluationId, ?string $userId, string $note = 'SLA resolution recorded by operator.'): array
    {
        $evaluation = $this->requireEvaluation($evaluationId);
        $now = gmdate('c');
        $context = $evaluation['context'];
        $context['manual_resolution_note'] = $note;
        $context['manual_resolution_by'] = $userId;
        $context['manual_resolution_at'] = $now;

        $responseAt = $evaluation['first_response_at'] ?: $now;
        $resolutionBreached = strtotime($now) > strtotime((string) $evaluation['resolution_due_at']);
        $stmt = $this->pdo->prepare('UPDATE platform_sla_evaluations SET first_response_at = COALESCE(first_response_at, :first_response_at), resolved_at = :resolved_at, response_breached = :response_breached, resolution_breached = :resolution_breached, status = :status, context_json = :context_json, evaluated_by = :evaluated_by, evaluated_at = :evaluated_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'first_response_at' => $responseAt,
            'resolved_at' => $now,
            'response_breached' => strtotime((string) $responseAt) > strtotime((string) $evaluation['response_due_at']) ? 1 : 0,
            'resolution_breached' => $resolutionBreached ? 1 : 0,
            'status' => $resolutionBreached ? 'breached' : 'met',
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'evaluated_by' => $userId,
            'evaluated_at' => $now,
            'updated_at' => $now,
            'id' => $evaluationId,
        ]);

        return $this->requireEvaluation($evaluationId);
    }

    /** @return list<array<string, mixed>> */
    public function operationalPolicies(string $status = 'all'): array
    {
        if (in_array($status, ['active', 'draft', 'archived'], true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_operational_policies WHERE status = :status ORDER BY scope ASC, title ASC');
            $stmt->execute(['status' => $status]);
            return array_map([$this, 'normalizeOperationalPolicy'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->query('SELECT * FROM platform_operational_policies ORDER BY CASE status WHEN \'active\' THEN 1 WHEN \'draft\' THEN 2 ELSE 3 END, scope ASC, title ASC');
        return array_map([$this, 'normalizeOperationalPolicy'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function attestPolicy(string $policyId, array $body, ?string $userId): array
    {
        $policy = $this->requireOperationalPolicy($policyId);
        $status = strtolower(trim((string) ($body['status'] ?? 'acknowledged')));
        if (!in_array($status, ['acknowledged', 'needs_review', 'approved'], true)) {
            throw new ValidationException(['status' => ['status must be acknowledged, needs_review or approved.']]);
        }

        $now = gmdate('c');
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_policy_attestations (id, policy_id, status, notes, attested_by, attested_at, created_at) VALUES (:id, :policy_id, :status, :notes, :attested_by, :attested_at, :created_at)');
        $stmt->execute([
            'id' => $id,
            'policy_id' => $policy['id'],
            'status' => $status,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'attested_by' => $userId,
            'attested_at' => $now,
            'created_at' => $now,
        ]);

        return $this->requireAttestation($id);
    }

    /** @return list<array<string, mixed>> */
    public function policyAttestations(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT a.*, p.policy_code, p.title AS policy_title, p.scope AS policy_scope FROM platform_policy_attestations a JOIN platform_operational_policies p ON p.id = a.policy_id ORDER BY a.attested_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAttestation'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    private function activeAlerts(): array
    {
        $stmt = $this->pdo->query("SELECT id, 'alert' AS source_type, name AS title, severity, status, opened_at AS opened_at, acknowledged_at AS first_response_at, resolved_at, metric, metric_value, threshold_value, message FROM platform_alerts WHERE status IN ('open', 'acknowledged') ORDER BY opened_at DESC LIMIT 100");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    private function activeIncidents(): array
    {
        $stmt = $this->pdo->query("SELECT id, 'incident' AS source_type, title, severity, status, opened_at AS opened_at, opened_at AS first_response_at, resolved_at, NULL AS metric, NULL AS metric_value, NULL AS threshold_value, summary AS message FROM platform_incidents WHERE status != 'resolved' ORDER BY opened_at DESC LIMIT 100");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $policy @return array<string, mixed> */
    private function evaluateSource(array $source, array $policy, ?string $userId): array
    {
        $openedAt = (string) ($source['opened_at'] ?? gmdate('c'));
        $responseDueAt = $this->addMinutes($openedAt, (int) $policy['response_minutes']);
        $resolutionDueAt = $this->addMinutes($openedAt, (int) $policy['resolution_minutes']);
        $firstResponseAt = $source['first_response_at'] ?: null;
        $resolvedAt = $source['resolved_at'] ?: null;
        $status = $this->statusFromDates($firstResponseAt, $resolvedAt, $responseDueAt, $resolutionDueAt);
        $responseBreached = $firstResponseAt === null && time() > strtotime($responseDueAt);
        if ($firstResponseAt !== null) {
            $responseBreached = strtotime((string) $firstResponseAt) > strtotime($responseDueAt);
        }
        $resolutionBreached = $resolvedAt === null && time() > strtotime($resolutionDueAt);
        if ($resolvedAt !== null) {
            $resolutionBreached = strtotime((string) $resolvedAt) > strtotime($resolutionDueAt);
        }

        $context = [
            'source_title' => $source['title'] ?? $source['name'] ?? 'Operational source',
            'source_status' => $source['status'] ?? 'unknown',
            'source_message' => $source['message'] ?? null,
            'metric' => $source['metric'] ?? null,
            'metric_value' => $source['metric_value'] ?? null,
            'threshold_value' => $source['threshold_value'] ?? null,
            'policy_name' => $policy['name'],
            'evaluated_at' => gmdate('c'),
        ];

        $existing = $this->evaluationFor((string) $source['source_type'], (string) $source['id'], (string) $policy['id']);
        $now = gmdate('c');
        if ($existing === null) {
            $id = Uuid::v4();
            $stmt = $this->pdo->prepare('INSERT INTO platform_sla_evaluations (id, source_type, source_id, policy_id, severity, status, response_due_at, resolution_due_at, first_response_at, resolved_at, response_breached, resolution_breached, context_json, evaluated_by, evaluated_at, created_at, updated_at) VALUES (:id, :source_type, :source_id, :policy_id, :severity, :status, :response_due_at, :resolution_due_at, :first_response_at, :resolved_at, :response_breached, :resolution_breached, :context_json, :evaluated_by, :evaluated_at, :created_at, :updated_at)');
            $stmt->execute([
                'id' => $id,
                'source_type' => $source['source_type'],
                'source_id' => $source['id'],
                'policy_id' => $policy['id'],
                'severity' => $source['severity'],
                'status' => $status,
                'response_due_at' => $responseDueAt,
                'resolution_due_at' => $resolutionDueAt,
                'first_response_at' => $firstResponseAt,
                'resolved_at' => $resolvedAt,
                'response_breached' => $responseBreached ? 1 : 0,
                'resolution_breached' => $resolutionBreached ? 1 : 0,
                'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'evaluated_by' => $userId,
                'evaluated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created = $this->requireEvaluation($id);
            $created['was_created'] = true;
            return $created;
        }

        $stmt = $this->pdo->prepare('UPDATE platform_sla_evaluations SET severity = :severity, status = :status, response_due_at = :response_due_at, resolution_due_at = :resolution_due_at, first_response_at = :first_response_at, resolved_at = :resolved_at, response_breached = :response_breached, resolution_breached = :resolution_breached, context_json = :context_json, evaluated_by = :evaluated_by, evaluated_at = :evaluated_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'severity' => $source['severity'],
            'status' => $status,
            'response_due_at' => $responseDueAt,
            'resolution_due_at' => $resolutionDueAt,
            'first_response_at' => $firstResponseAt,
            'resolved_at' => $resolvedAt,
            'response_breached' => $responseBreached ? 1 : 0,
            'resolution_breached' => $resolutionBreached ? 1 : 0,
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'evaluated_by' => $userId,
            'evaluated_at' => $now,
            'updated_at' => $now,
            'id' => $existing['id'],
        ]);
        $updated = $this->requireEvaluation((string) $existing['id']);
        $updated['was_created'] = false;
        return $updated;
    }

    private function statusFromDates(?string $firstResponseAt, ?string $resolvedAt, string $responseDueAt, string $resolutionDueAt): string
    {
        $now = time();
        if ($resolvedAt !== null && $resolvedAt !== '') {
            return (strtotime($resolvedAt) > strtotime($resolutionDueAt)) ? 'breached' : 'met';
        }
        if (($firstResponseAt === null || $firstResponseAt === '') && $now > strtotime($responseDueAt)) {
            return 'breached';
        }
        if ($now > strtotime($resolutionDueAt)) {
            return 'breached';
        }

        $remaining = strtotime($resolutionDueAt) - $now;
        $total = max(60, strtotime($resolutionDueAt) - (strtotime($responseDueAt) - 60));
        if ($remaining <= max(900, (int) floor($total * 0.20))) {
            return 'at_risk';
        }
        return 'within_sla';
    }

    /** @return array<string, mixed>|null */
    private function policyFor(string $sourceType, string $severity): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_sla_policies WHERE enabled = 1 AND source_type = :source_type AND severity = :severity ORDER BY response_minutes ASC LIMIT 1');
        $stmt->execute(['source_type' => $sourceType, 'severity' => $severity]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeSlaPolicy($row) : null;
    }

    /** @return array<string, mixed>|null */
    private function evaluationFor(string $sourceType, string $sourceId, string $policyId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT e.*, p.name AS policy_name, p.source_type AS policy_source_type FROM platform_sla_evaluations e JOIN platform_sla_policies p ON p.id = e.policy_id WHERE e.source_type = :source_type AND e.source_id = :source_id AND e.policy_id = :policy_id LIMIT 1');
        $stmt->execute(['source_type' => $sourceType, 'source_id' => $sourceId, 'policy_id' => $policyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeEvaluation($row) : null;
    }

    /** @return array<string, mixed> */
    private function requireEvaluation(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT e.*, p.name AS policy_name, p.source_type AS policy_source_type FROM platform_sla_evaluations e JOIN platform_sla_policies p ON p.id = e.policy_id WHERE e.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('SLA evaluation not found.');
        }
        return $this->normalizeEvaluation($row);
    }

    /** @return array<string, mixed> */
    private function requireOperationalPolicy(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_operational_policies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Operational policy not found.');
        }
        return $this->normalizeOperationalPolicy($row);
    }

    /** @return array<string, mixed> */
    private function requireAttestation(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, p.policy_code, p.title AS policy_title, p.scope AS policy_scope FROM platform_policy_attestations a JOIN platform_operational_policies p ON p.id = a.policy_id WHERE a.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Policy attestation not found.');
        }
        return $this->normalizeAttestation($row);
    }

    /** @return array<string, mixed> */
    private function slaSummary(): array
    {
        $summary = ['within_sla' => 0, 'at_risk' => 0, 'breached' => 0, 'met' => 0, 'total' => 0];
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM platform_sla_evaluations GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) $row['total'];
            }
            $summary['total'] += (int) $row['total'];
        }
        $summary['open_breaches'] = $summary['breached'];
        return $summary;
    }

    /** @return array<string, mixed> */
    private function policySummary(): array
    {
        $summary = ['active' => 0, 'draft' => 0, 'archived' => 0, 'total' => 0, 'attestations' => 0];
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM platform_operational_policies GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) $row['total'];
            }
            $summary['total'] += (int) $row['total'];
        }
        $summary['attestations'] = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_policy_attestations')->fetchColumn();
        return $summary;
    }

    /** @param list<array<string, mixed>> $breaches @param list<array<string, mixed>> $atRisk @param list<array<string, mixed>> $policies @return list<string> */
    private function operatorActions(array $breaches, array $atRisk, array $policies): array
    {
        $actions = [];
        if ($breaches !== []) {
            $actions[] = 'Review breached SLA evaluations and either respond, resolve or open an incident/status update.';
        }
        if ($atRisk !== []) {
            $actions[] = 'Check at-risk SLA items before they breach response or resolution targets.';
        }
        $drafts = array_values(array_filter($policies, static fn (array $policy): bool => $policy['status'] === 'draft'));
        if ($drafts !== []) {
            $actions[] = 'Review draft operational policies before external pilot onboarding.';
        }
        if ($actions === []) {
            $actions[] = 'Run SLA evaluation after incident/alert changes and attest the pilot readiness policy before demos.';
        }
        return $actions;
    }

    private function addMinutes(string $time, int $minutes): string
    {
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            $timestamp = time();
        }
        return gmdate('c', $timestamp + ($minutes * 60));
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeSlaPolicy(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'source_type' => (string) $row['source_type'],
            'severity' => (string) $row['severity'],
            'response_minutes' => (int) $row['response_minutes'],
            'resolution_minutes' => (int) $row['resolution_minutes'],
            'enabled' => (bool) $row['enabled'],
            'description' => $row['description'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeEvaluation(array $row): array
    {
        $context = json_decode((string) ($row['context_json'] ?? '{}'), true);
        return [
            'id' => (string) $row['id'],
            'source_type' => (string) $row['source_type'],
            'source_id' => (string) $row['source_id'],
            'policy_id' => (string) $row['policy_id'],
            'policy_name' => (string) ($row['policy_name'] ?? ''),
            'severity' => (string) $row['severity'],
            'status' => (string) $row['status'],
            'response_due_at' => (string) $row['response_due_at'],
            'resolution_due_at' => (string) $row['resolution_due_at'],
            'first_response_at' => $row['first_response_at'] ?? null,
            'resolved_at' => $row['resolved_at'] ?? null,
            'response_breached' => (bool) $row['response_breached'],
            'resolution_breached' => (bool) $row['resolution_breached'],
            'context' => is_array($context) ? $context : [],
            'evaluated_by' => $row['evaluated_by'] ?? null,
            'evaluated_at' => (string) $row['evaluated_at'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeOperationalPolicy(array $row): array
    {
        $requirements = json_decode((string) ($row['requirements_json'] ?? '[]'), true);
        return [
            'id' => (string) $row['id'],
            'policy_code' => (string) $row['policy_code'],
            'title' => (string) $row['title'],
            'scope' => (string) $row['scope'],
            'status' => (string) $row['status'],
            'version' => (string) $row['version'],
            'owner_role' => (string) $row['owner_role'],
            'review_due_at' => $row['review_due_at'] ?? null,
            'summary' => (string) $row['summary'],
            'requirements' => is_array($requirements) ? $requirements : [],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeAttestation(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'policy_id' => (string) $row['policy_id'],
            'policy_code' => (string) ($row['policy_code'] ?? ''),
            'policy_title' => (string) ($row['policy_title'] ?? ''),
            'policy_scope' => (string) ($row['policy_scope'] ?? ''),
            'status' => (string) $row['status'],
            'notes' => $row['notes'] ?? null,
            'attested_by' => $row['attested_by'] ?? null,
            'attested_at' => (string) $row['attested_at'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
