<?php

declare(strict_types=1);

namespace Reborn\Platform\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Platform\Application\BackupService;
use Reborn\Platform\Application\OperationalTelemetryService;
use Reborn\Platform\Application\ProductionReadinessService;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class PlatformController
{
    public function __construct(
        private readonly ProductionReadinessService $readiness,
        private readonly OperationalTelemetryService $telemetry,
        private readonly BackupService $backups,
        private readonly AuthContext $auth,
    ) {
    }

    public function ready(Request $request): JsonResponse
    {
        $readiness = $this->readiness->readiness();
        return JsonResponse::ok(['readiness' => $readiness], $request->requestId());
    }

    public function securityPolicy(Request $request): JsonResponse
    {
        return JsonResponse::ok(['security_policy' => $this->readiness->securityPolicy()], $request->requestId());
    }

    public function deployChecklist(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['deploy_checklist' => $this->readiness->deployChecklist()], $request->requestId());
    }

    public function runtime(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['runtime' => $this->readiness->runtimeReport()], $request->requestId());
    }

    public function storeReadinessSnapshot(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $snapshot = $this->readiness->recordSnapshot($user->id);
        return JsonResponse::created(['readiness_snapshot' => $snapshot], $request->requestId());
    }

    public function readinessSnapshots(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        return JsonResponse::ok(['readiness_snapshots' => $this->telemetry->readinessHistory($limit)], $request->requestId());
    }

    public function observability(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['observability' => $this->telemetry->dashboard()], $request->requestId());
    }

    public function httpMetrics(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['http_metrics' => $this->telemetry->httpMetrics($limit)], $request->requestId());
    }

    public function logs(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(300, (int) $request->query('limit', 80)));
        return JsonResponse::ok(['logs' => $this->telemetry->logs($limit)], $request->requestId());
    }

    public function backups(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        return JsonResponse::ok([
            'backup_status' => $this->backups->status(),
            'backups' => $this->backups->latest($limit),
        ], $request->requestId());
    }

    public function createBackup(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $backup = $this->backups->create($user->id, 'api');
        return JsonResponse::created(['backup' => $backup], $request->requestId());
    }

    public function deploymentRunbook(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['deployment_runbook' => $this->telemetry->deploymentRunbook()], $request->requestId());
    }

    public function smokeTestsSummary(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['smoke_tests' => $this->telemetry->smokeTestsSummary()], $request->requestId());
    }
}
