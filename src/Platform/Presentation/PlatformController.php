<?php

declare(strict_types=1);

namespace Reborn\Platform\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Platform\Application\BackupService;
use Reborn\Platform\Application\IncidentResponseService;
use Reborn\Platform\Application\OperationalTelemetryService;
use Reborn\Platform\Application\NotificationCenterService;
use Reborn\Platform\Application\OperationalGovernanceService;
use Reborn\Platform\Application\PrivacyGovernanceService;
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
        private readonly OperationalGovernanceService $governance,
        private readonly PrivacyGovernanceService $privacy,
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



    public function serviceGovernance(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['service_governance' => $this->governance->dashboard()], $request->requestId());
    }

    public function slaPolicies(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['sla_policies' => $this->governance->slaPolicies()], $request->requestId());
    }

    public function evaluateSlas(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['sla_evaluation_run' => $this->governance->evaluateSlas($user->id)], $request->requestId());
    }

    public function slaEvaluations(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['sla_evaluations' => $this->governance->slaEvaluations($status, $limit)], $request->requestId());
    }

    public function markSlaResponse(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $body = $request->body();
        $note = trim((string) ($body['note'] ?? 'First response recorded from Step 24 console.'));
        return JsonResponse::ok(['sla_evaluation' => $this->governance->markSlaResponse((string) $request->param('id'), $user->id, $note)], $request->requestId());
    }

    public function markSlaResolved(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $body = $request->body();
        $note = trim((string) ($body['note'] ?? 'SLA resolution recorded from Step 24 console.'));
        return JsonResponse::ok(['sla_evaluation' => $this->governance->markSlaResolved((string) $request->param('id'), $user->id, $note)], $request->requestId());
    }

    public function operationalPolicies(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['operational_policies' => $this->governance->operationalPolicies($status)], $request->requestId());
    }

    public function policyAttestations(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['policy_attestations' => $this->governance->policyAttestations($limit)], $request->requestId());
    }

    public function attestOperationalPolicy(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['policy_attestation' => $this->governance->attestPolicy((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }


    public function privacyGovernance(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['privacy_governance' => $this->privacy->dashboard()], $request->requestId());
    }

    public function privacyNotices(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['privacy_notices' => $this->privacy->privacyNotices($status)], $request->requestId());
    }

    public function consentRecords(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['consent_records' => $this->privacy->consentRecords($status, $limit)], $request->requestId());
    }

    public function recordConsent(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['consent_record' => $this->privacy->recordConsent($request->body(), $user->id)], $request->requestId());
    }

    public function withdrawConsent(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $body = $request->body();
        $note = trim((string) ($body['note'] ?? 'Consent withdrawn from Step 25 console.'));
        return JsonResponse::ok(['consent_record' => $this->privacy->withdrawConsent((string) $request->param('id'), $user->id, $note)], $request->requestId());
    }

    public function dataProcessingRecords(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['data_processing_records' => $this->privacy->processingRecords()], $request->requestId());
    }

    public function retentionRules(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['retention_rules' => $this->privacy->retentionRules()], $request->requestId());
    }

    public function evaluateRetention(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['retention_evaluation_run' => $this->privacy->evaluateRetention($user->id)], $request->requestId());
    }

    public function retentionEvaluations(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['retention_evaluations' => $this->privacy->retentionEvaluations($limit)], $request->requestId());
    }

    public function dataSubjectRequests(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['data_subject_requests' => $this->privacy->dataSubjectRequests($status, $limit)], $request->requestId());
    }

    public function createDataSubjectRequest(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['data_subject_request' => $this->privacy->createDataSubjectRequest($request->body(), $user->id)], $request->requestId());
    }

    public function resolveDataSubjectRequest(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['data_subject_request' => $this->privacy->resolveDataSubjectRequest((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function generateDataExport(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['data_export' => $this->privacy->generateDataExport((string) $request->param('id'), $user->id)], $request->requestId());
    }

    public function dataExports(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['data_exports' => $this->privacy->dataExports($limit)], $request->requestId());
    }

}
