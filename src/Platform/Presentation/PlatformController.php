<?php

declare(strict_types=1);

namespace Reborn\Platform\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Platform\Application\BackupService;
use Reborn\Platform\Application\IncidentResponseService;
use Reborn\Platform\Application\OperationalTelemetryService;
use Reborn\Platform\Application\NotificationCenterService;
use Reborn\Platform\Application\ProductionReadinessService;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class PlatformController
{
    public function __construct(
        private readonly ProductionReadinessService $readiness,
        private readonly OperationalTelemetryService $telemetry,
        private readonly BackupService $backups,
        private readonly IncidentResponseService $incidents,
        private readonly NotificationCenterService $notifications,
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
    public function incidentResponse(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['incident_response' => $this->incidents->dashboard()], $request->requestId());
    }

    public function alertRules(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['alert_rules' => $this->incidents->alertRules()], $request->requestId());
    }

    public function evaluateAlerts(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['alert_evaluation' => $this->incidents->evaluateAlerts($user->id)], $request->requestId());
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['alerts' => $this->incidents->alerts($status, $limit)], $request->requestId());
    }

    public function acknowledgeAlert(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['alert' => $this->incidents->acknowledgeAlert((string) $request->param('id'), $user->id)], $request->requestId());
    }

    public function resolveAlert(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $body = $request->body();
        $message = trim((string) ($body['message'] ?? 'Resolved by operator.'));
        return JsonResponse::ok(['alert' => $this->incidents->resolveAlert((string) $request->param('id'), $user->id, $message)], $request->requestId());
    }

    public function incidents(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['incidents' => $this->incidents->incidents($status, $limit)], $request->requestId());
    }

    public function createIncident(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['incident' => $this->incidents->createIncident($request->body(), $user->id)], $request->requestId());
    }

    public function updateIncidentStatus(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['incident' => $this->incidents->updateIncidentStatus((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function statusPage(Request $request): JsonResponse
    {
        return JsonResponse::ok(['status_page' => $this->incidents->statusPage()], $request->requestId());
    }

    public function statusUpdates(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        return JsonResponse::ok(['status_updates' => $this->incidents->statusUpdates($limit)], $request->requestId());
    }

    public function createStatusUpdate(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['status_update' => $this->incidents->createStatusUpdate($request->body(), $user->id)], $request->requestId());
    }

    public function maintenanceWindows(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['maintenance_windows' => $this->incidents->maintenanceWindows($status, $limit)], $request->requestId());
    }

    public function createMaintenanceWindow(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['maintenance_window' => $this->incidents->createMaintenanceWindow($request->body(), $user->id)], $request->requestId());
    }

    public function closeMaintenanceWindow(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['maintenance_window' => $this->incidents->closeMaintenanceWindow((string) $request->param('id'), $user->id)], $request->requestId());
    }


    public function notificationCenter(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['notification_center' => $this->notifications->dashboard()], $request->requestId());
    }

    public function notificationChannels(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['notification_channels' => $this->notifications->channels()], $request->requestId());
    }

    public function createNotificationChannel(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['notification_channel' => $this->notifications->createChannel($request->body(), $user->id)], $request->requestId());
    }

    public function notificationRules(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['notification_rules' => $this->notifications->notificationRules()], $request->requestId());
    }

    public function notificationDeliveries(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['notification_deliveries' => $this->notifications->deliveries($status, $limit)], $request->requestId());
    }

    public function dispatchNotifications(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['notification_dispatch' => $this->notifications->dispatch($request->body(), $user->id)], $request->requestId());
    }

    public function markNotificationDelivery(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $body = $request->body();
        $status = trim((string) ($body['status'] ?? 'sent'));
        $message = isset($body['message']) ? trim((string) $body['message']) : null;
        return JsonResponse::ok(['notification_delivery' => $this->notifications->markDelivery((string) $request->param('id'), $status, $message)], $request->requestId());
    }

    public function escalationPolicies(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['escalation_policies' => $this->notifications->escalationPolicies()], $request->requestId());
    }

    public function escalationRuns(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['escalation_runs' => $this->notifications->escalationRuns($status, $limit)], $request->requestId());
    }

    public function escalateIncident(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['incident_escalation' => $this->notifications->escalateIncident((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

}
