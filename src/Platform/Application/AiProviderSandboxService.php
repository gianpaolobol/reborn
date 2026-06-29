<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class AiProviderSandboxService
{
    /** @var list<string> */
    private array $jobTypes = ['repair_diagnosis', 'image_to_3d_model', 'local_3d_generation', 'mesh_refinement', 'model_validation'];

    /** @var list<string> */
    private array $jobStatuses = ['queued', 'running', 'succeeded', 'failed', 'retry_scheduled', 'cancelled'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'sandbox_version' => 'ai_provider_adapter_sandbox_job_orchestration_v1_step31',
            'generated_at' => gmdate('c'),
            'summary' => $this->summary(),
            'adapters' => $this->adapters('all'),
            'jobs' => $this->jobs('active', 20),
            'recent_events' => $this->jobEvents(null, 20),
            'artifact_stubs' => $this->artifactStubs(20),
            'cost_ledger' => $this->costLedger(20),
            'operator_actions' => $this->operatorActions(),
            'important_notes' => [
                'Step 31 does not call external AI providers and does not generate real STL/CAD files.',
                'Adapters are sandbox records that make future Meshy, Trellis or Rodin integrations governable before activation.',
                'Every produced artifact is a placeholder stub and remains subject to human review from Step 30.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $reserved = (int) $this->scalar("SELECT COALESCE(SUM(amount_cents), 0) FROM platform_ai_provider_cost_ledger WHERE direction = 'reserved'");
        $spent = (int) $this->scalar("SELECT COALESCE(SUM(amount_cents), 0) FROM platform_ai_provider_cost_ledger WHERE direction = 'spent'");

        return [
            'adapters_total' => $this->count('platform_ai_provider_adapters'),
            'adapters_sandbox' => $this->count('platform_ai_provider_adapters', "status = 'sandbox'"),
            'adapters_secret_missing' => $this->count('platform_ai_provider_adapters', "requires_secret = 1 AND secret_status <> 'configured'"),
            'jobs_total' => $this->count('platform_ai_orchestration_jobs'),
            'jobs_active' => $this->count('platform_ai_orchestration_jobs', "status IN ('queued', 'running', 'retry_scheduled')"),
            'jobs_failed' => $this->count('platform_ai_orchestration_jobs', "status = 'failed'"),
            'artifact_stubs_total' => $this->count('platform_ai_artifact_stubs'),
            'artifact_stubs_review_required' => $this->count('platform_ai_artifact_stubs', "review_required = 1"),
            'reserved_cost_cents' => $reserved,
            'spent_cost_cents' => $spent,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function adapters(string $status = 'all'): array
    {
        $sql = 'SELECT * FROM platform_ai_provider_adapters';
        $params = [];
        if (in_array($status, ['sandbox', 'ready', 'disabled'], true)) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE status WHEN \'ready\' THEN 1 WHEN \'sandbox\' THEN 2 ELSE 3 END, provider_key, capability';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'normalizeAdapter'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function runHealthCheck(?string $userId): array
    {
        $adapters = $this->adapters('all');
        $results = [];
        $now = gmdate('c');

        foreach ($adapters as $adapter) {
            $health = 'ok';
            $message = 'Sandbox adapter is configured for dry-run orchestration.';

            if ($adapter['status'] === 'disabled') {
                $health = 'disabled';
                $message = 'Adapter is disabled by governance policy.';
            } elseif ($adapter['requires_secret'] && $adapter['secret_status'] !== 'configured') {
                $health = 'blocked_missing_secret';
                $message = 'Adapter requires a secret before live mode can be considered.';
            } elseif ($adapter['mode'] !== 'mock') {
                $health = 'review_required';
                $message = 'Non-mock mode requires explicit release gate and human approval.';
            }

            $stmt = $this->pdo->prepare('UPDATE platform_ai_provider_adapters SET last_health_status = :health, last_checked_at = :checked_at, updated_at = :updated_at WHERE adapter_key = :adapter_key');
            $stmt->execute([
                'health' => $health,
                'checked_at' => $now,
                'updated_at' => $now,
                'adapter_key' => $adapter['adapter_key'],
            ]);

            $results[] = [
                'adapter_key' => $adapter['adapter_key'],
                'provider_key' => $adapter['provider_key'],
                'health' => $health,
                'message' => $message,
            ];
        }

        $this->audit('ai_adapter_health_checked', 'ai_provider_adapter', null, 'AI provider sandbox health check completed.', ['results' => $results], $userId);

        return [
            'checked_at' => $now,
            'total' => count($results),
            'blocked' => count(array_filter($results, static fn (array $row): bool => $row['health'] === 'blocked_missing_secret')),
            'results' => $results,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function jobs(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT j.*, a.name AS adapter_name, a.provider_key, a.mode AS adapter_mode FROM platform_ai_orchestration_jobs j LEFT JOIN platform_ai_provider_adapters a ON a.adapter_key = j.adapter_key';
        $params = [];
        if ($status === 'active') {
            $sql .= " WHERE j.status IN ('queued', 'running', 'retry_scheduled')";
        } elseif (in_array($status, $this->jobStatuses, true)) {
            $sql .= ' WHERE j.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE j.status WHEN \'running\' THEN 1 WHEN \'queued\' THEN 2 WHEN \'retry_scheduled\' THEN 3 WHEN \'failed\' THEN 4 ELSE 5 END, j.priority ASC, j.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeJob'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createJob(array $body, ?string $userId): array
    {
        $adapterKey = trim((string) ($body['adapter_key'] ?? 'mock_meshy_image_to_3d'));
        $adapter = $this->requireAdapter($adapterKey);

        $jobType = strtolower(trim((string) ($body['job_type'] ?? $adapter['capability'])));
        if (!in_array($jobType, $this->jobTypes, true)) {
            throw new ValidationException(['job_type' => ['job_type is not supported by the sandbox.']]);
        }

        $inputSummary = trim((string) ($body['input_summary'] ?? ''));
        if ($inputSummary === '') {
            throw new ValidationException(['input_summary' => ['input_summary is required.']]);
        }

        $priority = max(1, min(100, (int) ($body['priority'] ?? 50)));
        $maxAttempts = max(1, min(5, (int) ($body['max_attempts'] ?? $adapter['retry_limit'])));
        $estimatedCost = max(0, (int) ($body['estimated_cost_cents'] ?? $adapter['cost_per_job_cents']));
        $id = Uuid::v4();
        $now = gmdate('c');
        $jobCode = 'AI-JOB-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));

        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_orchestration_jobs (id, job_code, adapter_key, pipeline_run_id, job_type, status, priority, attempts, max_attempts, input_summary, provider_request_ref, provider_response_ref, estimated_cost_cents, actual_cost_cents, error_message, scheduled_at, started_at, finished_at, created_by, created_at, updated_at) VALUES (:id, :job_code, :adapter_key, :pipeline_run_id, :job_type, :status, :priority, :attempts, :max_attempts, :input_summary, :provider_request_ref, :provider_response_ref, :estimated_cost_cents, :actual_cost_cents, :error_message, :scheduled_at, :started_at, :finished_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'job_code' => $jobCode,
            'adapter_key' => $adapterKey,
            'pipeline_run_id' => trim((string) ($body['pipeline_run_id'] ?? '')) ?: null,
            'job_type' => $jobType,
            'status' => 'queued',
            'priority' => $priority,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'input_summary' => $inputSummary,
            'provider_request_ref' => trim((string) ($body['provider_request_ref'] ?? '')) ?: null,
            'provider_response_ref' => null,
            'estimated_cost_cents' => $estimatedCost,
            'actual_cost_cents' => 0,
            'error_message' => null,
            'scheduled_at' => trim((string) ($body['scheduled_at'] ?? '')) ?: $now,
            'started_at' => null,
            'finished_at' => null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->event($id, 'job_queued', 'AI provider sandbox job queued.', ['adapter_key' => $adapterKey, 'job_type' => $jobType], $userId);
        if ($estimatedCost > 0) {
            $this->ledger($adapterKey, $id, $estimatedCost, 'reserved', 'Estimated sandbox provider cost reserved for queued job.');
        }
        $this->audit('ai_sandbox_job_created', 'ai_orchestration_job', $id, sprintf('AI sandbox job %s created.', $jobCode), ['adapter_key' => $adapterKey, 'job_type' => $jobType], $userId);

        return $this->requireJob($id);
    }

    /** @return array<string, mixed> */
    public function advanceJob(string $id, array $body, ?string $userId): array
    {
        $job = $this->requireJob($id);
        $nextStatus = strtolower(trim((string) ($body['status'] ?? 'running')));
        if (!in_array($nextStatus, ['running', 'succeeded', 'failed'], true)) {
            throw new ValidationException(['status' => ['status must be running, succeeded or failed.']]);
        }

        if (in_array($job['status'], ['succeeded', 'failed', 'cancelled'], true)) {
            throw new ValidationException(['job' => ['terminal jobs cannot be advanced.']]);
        }

        $now = gmdate('c');
        $attempts = (int) $job['attempts'];
        $startedAt = $job['started_at'];
        $finishedAt = $job['finished_at'];
        $actualCost = (int) $job['actual_cost_cents'];
        $error = null;
        $providerResponseRef = $job['provider_response_ref'];

        if ($nextStatus === 'running') {
            $attempts++;
            $startedAt = $startedAt ?: $now;
            $message = 'AI sandbox job moved to running. External provider call is still mocked.';
            $eventType = 'job_running';
        } elseif ($nextStatus === 'succeeded') {
            $finishedAt = $now;
            $actualCost = max((int) ($body['actual_cost_cents'] ?? $job['estimated_cost_cents']), 0);
            $providerResponseRef = trim((string) ($body['provider_response_ref'] ?? 'mock-response-' . substr($job['job_code'], -6)));
            $message = 'AI sandbox job marked as succeeded and artifact stub created.';
            $eventType = 'job_succeeded';
        } else {
            $finishedAt = $now;
            $error = trim((string) ($body['error_message'] ?? 'Sandbox job failed during operator simulation.'));
            $message = 'AI sandbox job marked as failed.';
            $eventType = 'job_failed';
        }

        $stmt = $this->pdo->prepare('UPDATE platform_ai_orchestration_jobs SET status = :status, attempts = :attempts, provider_response_ref = :provider_response_ref, actual_cost_cents = :actual_cost_cents, error_message = :error_message, started_at = :started_at, finished_at = :finished_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $nextStatus,
            'attempts' => $attempts,
            'provider_response_ref' => $providerResponseRef,
            'actual_cost_cents' => $actualCost,
            'error_message' => $error,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'updated_at' => $now,
            'id' => $id,
        ]);

        if ($nextStatus === 'succeeded') {
            $this->createArtifactStub($id, $job['job_type'], $userId);
            if ($actualCost > 0) {
                $this->ledger((string) $job['adapter_key'], $id, $actualCost, 'spent', 'Sandbox provider cost marked as spent after successful mock job.');
            }
        }

        $this->event($id, $eventType, $message, ['status' => $nextStatus, 'external_calls' => false], $userId);
        $this->audit('ai_sandbox_job_advanced', 'ai_orchestration_job', $id, $message, ['status' => $nextStatus], $userId);
        return $this->requireJob($id);
    }

    /** @return array<string, mixed> */
    public function retryJob(string $id, ?string $userId): array
    {
        $job = $this->requireJob($id);
        if (!in_array($job['status'], ['failed', 'retry_scheduled'], true)) {
            throw new ValidationException(['job' => ['only failed or retry_scheduled jobs can be retried.']]);
        }
        if ((int) $job['attempts'] >= (int) $job['max_attempts']) {
            throw new ValidationException(['job' => ['max_attempts has already been reached.']]);
        }

        $now = gmdate('c');
        $stmt = $this->pdo->prepare("UPDATE platform_ai_orchestration_jobs SET status = 'queued', error_message = NULL, scheduled_at = :scheduled_at, finished_at = NULL, updated_at = :updated_at WHERE id = :id");
        $stmt->execute(['scheduled_at' => $now, 'updated_at' => $now, 'id' => $id]);
        $this->event($id, 'job_retry_queued', 'AI sandbox job queued for retry.', ['attempts' => (int) $job['attempts']], $userId);
        $this->audit('ai_sandbox_job_retry_queued', 'ai_orchestration_job', $id, 'AI sandbox job retry queued.', [], $userId);
        return $this->requireJob($id);
    }

    /** @return array<string, mixed> */
    public function cancelJob(string $id, array $body, ?string $userId): array
    {
        $job = $this->requireJob($id);
        if (in_array($job['status'], ['succeeded', 'failed', 'cancelled'], true)) {
            throw new ValidationException(['job' => ['terminal jobs cannot be cancelled.']]);
        }
        $reason = trim((string) ($body['reason'] ?? 'Cancelled by operator from Step 31 console.'));
        $now = gmdate('c');
        $stmt = $this->pdo->prepare("UPDATE platform_ai_orchestration_jobs SET status = 'cancelled', error_message = :reason, finished_at = :finished_at, updated_at = :updated_at WHERE id = :id");
        $stmt->execute(['reason' => $reason, 'finished_at' => $now, 'updated_at' => $now, 'id' => $id]);
        $this->event($id, 'job_cancelled', $reason, [], $userId);
        $this->audit('ai_sandbox_job_cancelled', 'ai_orchestration_job', $id, $reason, [], $userId);
        return $this->requireJob($id);
    }

    /** @return list<array<string, mixed>> */
    public function jobEvents(?string $jobId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT e.*, j.job_code FROM platform_ai_job_events e LEFT JOIN platform_ai_orchestration_jobs j ON j.id = e.job_id';
        $params = [];
        if ($jobId !== null && $jobId !== '') {
            $sql .= ' WHERE e.job_id = :job_id';
            $params['job_id'] = $jobId;
        }
        $sql .= ' ORDER BY e.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeEvent'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function artifactStubs(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT a.*, j.job_code, j.job_type FROM platform_ai_artifact_stubs a LEFT JOIN platform_ai_orchestration_jobs j ON j.id = a.job_id ORDER BY a.created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeArtifact'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function costLedger(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT l.*, j.job_code FROM platform_ai_provider_cost_ledger l LEFT JOIN platform_ai_orchestration_jobs j ON j.id = l.job_id ORDER BY l.created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeLedger'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_ai_provider_sandbox_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAudit'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, string> */
    private function operatorActions(): array
    {
        return [
            'run_health_check' => 'Evaluate sandbox adapter health without external calls.',
            'create_job' => 'Queue a mock AI job for diagnosis, image-to-3D or model validation.',
            'advance_job' => 'Move a queued job through running, succeeded or failed states.',
            'inspect_costs' => 'Review reserved/spent provider costs before enabling live integrations.',
            'review_artifacts' => 'Treat every artifact as a stub that requires Step 30 human review.',
        ];
    }

    /** @return array<string, mixed> */
    private function requireAdapter(string $adapterKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_ai_provider_adapters WHERE adapter_key = :adapter_key');
        $stmt->execute(['adapter_key' => $adapterKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('AI provider adapter not found.');
        }
        return $this->normalizeAdapter($row);
    }

    /** @return array<string, mixed> */
    private function requireJob(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT j.*, a.name AS adapter_name, a.provider_key, a.mode AS adapter_mode FROM platform_ai_orchestration_jobs j LEFT JOIN platform_ai_provider_adapters a ON a.adapter_key = j.adapter_key WHERE j.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('AI orchestration job not found.');
        }
        return $this->normalizeJob($row);
    }

    private function createArtifactStub(string $jobId, string $jobType, ?string $userId): void
    {
        $exists = (int) $this->scalar('SELECT COUNT(*) FROM platform_ai_artifact_stubs WHERE job_id = :job_id', ['job_id' => $jobId]);
        if ($exists > 0) {
            return;
        }
        $artifactType = match ($jobType) {
            'image_to_3d_model', 'local_3d_generation', 'mesh_refinement' => 'model_3d_placeholder',
            'repair_diagnosis' => 'diagnosis_report_placeholder',
            default => 'ai_output_placeholder',
        };
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_artifact_stubs (id, job_id, artifact_type, status, storage_ref, review_required, checksum, metadata_json, created_at) VALUES (:id, :job_id, :artifact_type, :status, :storage_ref, :review_required, :checksum, :metadata_json, :created_at)');
        $stmt->execute([
            'id' => $id,
            'job_id' => $jobId,
            'artifact_type' => $artifactType,
            'status' => 'placeholder_created',
            'storage_ref' => 'storage/ai-artifacts/placeholders/' . $id . '.json',
            'review_required' => 1,
            'checksum' => substr(hash('sha256', $jobId . $artifactType . $now), 0, 32),
            'metadata_json' => json_encode(['external_calls' => false, 'requires_human_review' => true], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
        $this->event($jobId, 'artifact_stub_created', 'AI output artifact placeholder created for human review.', ['artifact_id' => $id, 'artifact_type' => $artifactType], $userId);
    }

    private function event(string $jobId, string $eventType, string $message, array $payload = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_job_events (id, job_id, event_type, message, payload_json, actor_user_id, created_at) VALUES (:id, :job_id, :event_type, :message, :payload_json, :actor_user_id, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'job_id' => $jobId,
            'event_type' => $eventType,
            'message' => $message,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'actor_user_id' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }

    private function ledger(string $adapterKey, string $jobId, int $amountCents, string $direction, string $description): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_provider_cost_ledger (id, adapter_key, job_id, amount_cents, currency, direction, description, created_at) VALUES (:id, :adapter_key, :job_id, :amount_cents, :currency, :direction, :description, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'adapter_key' => $adapterKey,
            'job_id' => $jobId,
            'amount_cents' => $amountCents,
            'currency' => 'EUR',
            'direction' => $direction,
            'description' => $description,
            'created_at' => gmdate('c'),
        ]);
    }

    private function audit(string $action, ?string $subjectType, ?string $subjectId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_ai_provider_sandbox_audit_log (id, action, subject_type, subject_id, message, metadata_json, actor_user_id, created_at) VALUES (:id, :action, :subject_type, :subject_id, :message, :metadata_json, :actor_user_id, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'message' => $message,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'actor_user_id' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }

    private function count(string $table, ?string $where = null): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : ''));
    }

    /** @param array<string, mixed> $params */
    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function normalizeAdapter(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'adapter_key' => (string) $row['adapter_key'],
            'provider_key' => (string) $row['provider_key'],
            'name' => (string) $row['name'],
            'capability' => (string) $row['capability'],
            'mode' => (string) $row['mode'],
            'status' => (string) $row['status'],
            'requires_secret' => ((int) $row['requires_secret']) === 1,
            'secret_status' => (string) $row['secret_status'],
            'daily_budget_cents' => (int) $row['daily_budget_cents'],
            'cost_per_job_cents' => (int) $row['cost_per_job_cents'],
            'concurrency_limit' => (int) $row['concurrency_limit'],
            'timeout_seconds' => (int) $row['timeout_seconds'],
            'retry_limit' => (int) $row['retry_limit'],
            'last_health_status' => $row['last_health_status'] ?? null,
            'last_checked_at' => $row['last_checked_at'] ?? null,
            'notes' => $row['notes'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeJob(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'job_code' => (string) $row['job_code'],
            'adapter_key' => (string) $row['adapter_key'],
            'adapter_name' => $row['adapter_name'] ?? null,
            'provider_key' => $row['provider_key'] ?? null,
            'adapter_mode' => $row['adapter_mode'] ?? null,
            'pipeline_run_id' => $row['pipeline_run_id'] ?? null,
            'job_type' => (string) $row['job_type'],
            'status' => (string) $row['status'],
            'priority' => (int) $row['priority'],
            'attempts' => (int) $row['attempts'],
            'max_attempts' => (int) $row['max_attempts'],
            'input_summary' => (string) $row['input_summary'],
            'provider_request_ref' => $row['provider_request_ref'] ?? null,
            'provider_response_ref' => $row['provider_response_ref'] ?? null,
            'estimated_cost_cents' => (int) $row['estimated_cost_cents'],
            'actual_cost_cents' => (int) $row['actual_cost_cents'],
            'error_message' => $row['error_message'] ?? null,
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'started_at' => $row['started_at'] ?? null,
            'finished_at' => $row['finished_at'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeEvent(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'job_id' => (string) $row['job_id'],
            'job_code' => $row['job_code'] ?? null,
            'event_type' => (string) $row['event_type'],
            'message' => (string) $row['message'],
            'payload' => $row['payload_json'] ? json_decode((string) $row['payload_json'], true) : null,
            'actor_user_id' => $row['actor_user_id'] ?? null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeArtifact(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'job_id' => (string) $row['job_id'],
            'job_code' => $row['job_code'] ?? null,
            'job_type' => $row['job_type'] ?? null,
            'artifact_type' => (string) $row['artifact_type'],
            'status' => (string) $row['status'],
            'storage_ref' => $row['storage_ref'] ?? null,
            'review_required' => ((int) $row['review_required']) === 1,
            'checksum' => $row['checksum'] ?? null,
            'metadata' => $row['metadata_json'] ? json_decode((string) $row['metadata_json'], true) : null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeLedger(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'adapter_key' => (string) $row['adapter_key'],
            'job_id' => $row['job_id'] ?? null,
            'job_code' => $row['job_code'] ?? null,
            'amount_cents' => (int) $row['amount_cents'],
            'currency' => (string) $row['currency'],
            'direction' => (string) $row['direction'],
            'description' => (string) $row['description'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeAudit(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'action' => (string) $row['action'],
            'subject_type' => $row['subject_type'] ?? null,
            'subject_id' => $row['subject_id'] ?? null,
            'message' => (string) $row['message'],
            'metadata' => $row['metadata_json'] ? json_decode((string) $row['metadata_json'], true) : null,
            'actor_user_id' => $row['actor_user_id'] ?? null,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
