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
use Reborn\Platform\Application\ReleaseManagementService;
use Reborn\Platform\Application\PartnerOnboardingService;
use Reborn\Platform\Application\MarketplaceRevenueService;
use Reborn\Platform\Application\MakerEconomyService;
use Reborn\Platform\Application\AiPipelineGovernanceService;
use Reborn\Platform\Application\AiProviderSandboxService;
use Reborn\Platform\Application\GeometryPrintabilityService;
use Reborn\Platform\Application\ProviderRoutingGovernanceService;
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
        private readonly ReleaseManagementService $releases,
        private readonly PartnerOnboardingService $partners,
        private readonly MarketplaceRevenueService $revenue,
        private readonly MakerEconomyService $makerEconomy,
        private readonly AiPipelineGovernanceService $aiGovernance,
        private readonly AiProviderSandboxService $aiSandbox,
        private readonly GeometryPrintabilityService $geometry,
        private readonly ProviderRoutingGovernanceService $routing,
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


    public function releaseManagement(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['release_management' => $this->releases->dashboard()], $request->requestId());
    }

    public function betaReadiness(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['beta_readiness' => $this->releases->betaReadiness()], $request->requestId());
    }

    public function featureFlags(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['feature_flags' => $this->releases->featureFlags($status)], $request->requestId());
    }

    public function updateFeatureFlag(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['feature_flag' => $this->releases->updateFeatureFlag((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function releases(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['releases' => $this->releases->releases($status, $limit)], $request->requestId());
    }

    public function createRelease(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['release' => $this->releases->createRelease($request->body(), $user->id)], $request->requestId());
    }

    public function evaluateReleaseGates(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['release_gate_evaluation' => $this->releases->evaluateReleaseGates((string) $request->param('id'), $user->id)], $request->requestId());
    }

    public function releaseGates(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['release_gates' => $this->releases->releaseGates((string) $request->param('id'))], $request->requestId());
    }

    public function decideRelease(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['release_decision' => $this->releases->decideRelease((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function releaseDecisions(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['release_decisions' => $this->releases->releaseDecisions($limit)], $request->requestId());
    }

    public function pilotCohorts(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['pilot_cohorts' => $this->releases->pilotCohorts($status)], $request->requestId());
    }

    public function updatePilotCohort(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['pilot_cohort' => $this->releases->updatePilotCohort((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function pilotParticipants(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['pilot_participants' => $this->releases->pilotParticipants($status, $limit)], $request->requestId());
    }

    public function addPilotParticipant(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['pilot_participant' => $this->releases->addPilotParticipant($request->body(), $user->id)], $request->requestId());
    }

    public function updatePilotParticipant(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['pilot_participant' => $this->releases->updatePilotParticipant((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }


    public function partnerOnboarding(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['partner_onboarding' => $this->partners->dashboard()], $request->requestId());
    }

    public function partners(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['partners' => $this->partners->partners($status, $limit)], $request->requestId());
    }

    public function createPartner(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['partner' => $this->partners->createPartner($request->body(), $user->id)], $request->requestId());
    }

    public function partnerReadiness(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['partner_readiness' => $this->partners->partnerReadiness((string) $request->param('id'))], $request->requestId());
    }

    public function evaluatePartnerReadiness(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['partner_readiness_review' => $this->partners->evaluatePartnerReadiness((string) $request->param('id'), $user->id)], $request->requestId());
    }

    public function partnerTasks(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['partner_tasks' => $this->partners->tasks($status, $limit)], $request->requestId());
    }

    public function updatePartnerTaskStatus(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['partner_task' => $this->partners->updateTaskStatus((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function partnerAgreements(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['partner_agreements' => $this->partners->agreements($status, $limit)], $request->requestId());
    }

    public function createPartnerAgreement(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['partner_agreement' => $this->partners->createAgreement((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function updatePartnerAgreementStatus(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['partner_agreement' => $this->partners->updateAgreementStatus((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function partnerIntegrations(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['partner_integrations' => $this->partners->integrations($status, $limit)], $request->requestId());
    }

    public function createPartnerIntegration(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['partner_integration' => $this->partners->createIntegration((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function updatePartnerIntegrationStatus(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['partner_integration' => $this->partners->updateIntegrationStatus((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function partnerReadinessReviews(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['partner_readiness_reviews' => $this->partners->readinessReviews($limit)], $request->requestId());
    }


    public function marketplaceRevenue(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['marketplace_revenue' => $this->revenue->dashboard()], $request->requestId());
    }

    public function marketplaceFeePolicies(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['fee_policies' => $this->revenue->feePolicies($status)], $request->requestId());
    }

    public function creditAccounts(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['credit_accounts' => $this->revenue->creditAccounts($status, $limit)], $request->requestId());
    }

    public function createCreditAccount(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['credit_account' => $this->revenue->createCreditAccount($request->body(), $user->id)], $request->requestId());
    }

    public function creditTransactions(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $accountId = trim((string) $request->query('account_id', '')) ?: null;
        return JsonResponse::ok(['credit_transactions' => $this->revenue->creditTransactions($limit, $accountId)], $request->requestId());
    }

    public function recordCreditTransaction(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['credit_transaction' => $this->revenue->recordCreditTransaction($request->body(), $user->id)], $request->requestId());
    }

    public function payoutAccounts(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['payout_accounts' => $this->revenue->payoutAccounts($status, $limit)], $request->requestId());
    }

    public function createPayoutAccount(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['payout_account' => $this->revenue->createPayoutAccount($request->body(), $user->id)], $request->requestId());
    }

    public function payoutRuns(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['payout_runs' => $this->revenue->payoutRuns($status, $limit)], $request->requestId());
    }

    public function evaluatePayoutRun(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['payout_run_evaluation' => $this->revenue->evaluatePayoutRun($request->body(), $user->id)], $request->requestId());
    }

    public function approvePayoutRun(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['payout_run' => $this->revenue->approvePayoutRun((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function markPayoutRunPaid(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['payout_run' => $this->revenue->markPayoutRunPaid((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function payoutItems(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $runId = trim((string) $request->query('payout_run_id', '')) ?: null;
        return JsonResponse::ok(['payout_items' => $this->revenue->payoutItems($runId, $limit)], $request->requestId());
    }

    public function revenueAuditLog(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['revenue_audit_log' => $this->revenue->auditLog($limit)], $request->requestId());
    }


    public function makerEconomy(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['maker_economy' => $this->makerEconomy->dashboard()], $request->requestId());
    }

    public function makerProfiles(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['maker_profiles' => $this->makerEconomy->makerProfiles($status, $limit)], $request->requestId());
    }

    public function createMakerProfile(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['maker_profile' => $this->makerEconomy->createMakerProfile($request->body(), $user->id)], $request->requestId());
    }

    public function updateMakerProfileStatus(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['maker_profile' => $this->makerEconomy->updateMakerProfileStatus((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function modelAssets(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['model_assets' => $this->makerEconomy->modelAssets($status, $limit)], $request->requestId());
    }

    public function submitModelAsset(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['model_asset' => $this->makerEconomy->submitModelAsset($request->body(), $user->id)], $request->requestId());
    }

    public function reviewModelAsset(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['model_asset' => $this->makerEconomy->reviewModelAsset((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function modelLicenses(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['model_licenses' => $this->makerEconomy->modelLicenses($status)], $request->requestId());
    }

    public function modelDownloads(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $modelAssetId = trim((string) $request->query('model_asset_id', '')) ?: null;
        return JsonResponse::ok(['model_downloads' => $this->makerEconomy->modelDownloads($limit, $modelAssetId)], $request->requestId());
    }

    public function recordModelDownload(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['model_download_record' => $this->makerEconomy->recordModelDownload($request->body(), $user->id)], $request->requestId());
    }

    public function modelRoyaltyEvents(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $makerProfileId = trim((string) $request->query('maker_profile_id', '')) ?: null;
        return JsonResponse::ok(['model_royalty_events' => $this->makerEconomy->royaltyEvents($limit, $makerProfileId)], $request->requestId());
    }

    public function repairBounties(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['repair_bounties' => $this->makerEconomy->repairBounties($status, $limit)], $request->requestId());
    }

    public function createRepairBounty(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['repair_bounty' => $this->makerEconomy->createRepairBounty($request->body(), $user->id)], $request->requestId());
    }

    public function bountySubmissions(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $bountyId = trim((string) $request->query('bounty_id', '')) ?: null;
        return JsonResponse::ok(['bounty_submissions' => $this->makerEconomy->bountySubmissions($bountyId, $limit)], $request->requestId());
    }

    public function submitBounty(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['bounty_submission' => $this->makerEconomy->submitBounty($request->body(), $user->id)], $request->requestId());
    }

    public function reviewBountySubmission(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['bounty_submission' => $this->makerEconomy->reviewBountySubmission((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function makerEconomyAuditLog(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['maker_economy_audit_log' => $this->makerEconomy->auditLog($limit)], $request->requestId());
    }


    public function aiPipelineGovernance(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['ai_governance' => $this->aiGovernance->dashboard()], $request->requestId());
    }

    public function aiModelProviders(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['ai_model_providers' => $this->aiGovernance->modelProviders($status)], $request->requestId());
    }

    public function aiPipelineRuns(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_pipeline_runs' => $this->aiGovernance->pipelineRuns($status, $limit)], $request->requestId());
    }

    public function createAiPipelineRun(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['ai_pipeline_run' => $this->aiGovernance->createPipelineRun($request->body(), $user->id)], $request->requestId());
    }

    public function reviewAiPipelineRun(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['ai_pipeline_run' => $this->aiGovernance->reviewPipelineRun((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function aiHumanReviews(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $pipelineRunId = trim((string) $request->query('pipeline_run_id', '')) ?: null;
        return JsonResponse::ok(['ai_human_reviews' => $this->aiGovernance->humanReviews($pipelineRunId, $limit)], $request->requestId());
    }

    public function aiDatasetItems(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_dataset_items' => $this->aiGovernance->datasetItems($status, $limit)], $request->requestId());
    }

    public function createAiDatasetItem(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['ai_dataset_item' => $this->aiGovernance->createDatasetItem($request->body(), $user->id)], $request->requestId());
    }

    public function aiQualityEvaluations(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_quality_evaluations' => $this->aiGovernance->qualityEvaluations($limit)], $request->requestId());
    }

    public function evaluateAiQuality(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['ai_quality_evaluation' => $this->aiGovernance->evaluateQuality($request->body(), $user->id)], $request->requestId());
    }

    public function aiSafetyRules(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        return JsonResponse::ok(['ai_safety_rules' => $this->aiGovernance->safetyRules($status)], $request->requestId());
    }

    public function aiGovernanceAuditLog(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_governance_audit_log' => $this->aiGovernance->auditLog($limit)], $request->requestId());
    }


    public function aiProviderSandbox(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['ai_provider_sandbox' => $this->aiSandbox->dashboard()], $request->requestId());
    }

    public function aiProviderAdapters(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        return JsonResponse::ok(['ai_provider_adapters' => $this->aiSandbox->adapters($status)], $request->requestId());
    }

    public function checkAiProviderAdapters(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['ai_adapter_health_check' => $this->aiSandbox->runHealthCheck($user->id)], $request->requestId());
    }

    public function aiOrchestrationJobs(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_orchestration_jobs' => $this->aiSandbox->jobs($status, $limit)], $request->requestId());
    }

    public function createAiOrchestrationJob(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['ai_orchestration_job' => $this->aiSandbox->createJob($request->body(), $user->id)], $request->requestId());
    }

    public function advanceAiOrchestrationJob(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['ai_orchestration_job' => $this->aiSandbox->advanceJob((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function retryAiOrchestrationJob(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['ai_orchestration_job' => $this->aiSandbox->retryJob((string) $request->param('id'), $user->id)], $request->requestId());
    }

    public function cancelAiOrchestrationJob(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['ai_orchestration_job' => $this->aiSandbox->cancelJob((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function aiJobEvents(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $jobId = trim((string) $request->query('job_id', '')) ?: null;
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_job_events' => $this->aiSandbox->jobEvents($jobId, $limit)], $request->requestId());
    }

    public function aiArtifactStubs(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_artifact_stubs' => $this->aiSandbox->artifactStubs($limit)], $request->requestId());
    }

    public function aiProviderCostLedger(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_provider_cost_ledger' => $this->aiSandbox->costLedger($limit)], $request->requestId());
    }

    public function aiProviderSandboxAuditLog(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['ai_provider_sandbox_audit_log' => $this->aiSandbox->auditLog($limit)], $request->requestId());
    }


    public function geometryPrintability(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['geometry_printability' => $this->geometry->dashboard()], $request->requestId());
    }

    public function geometryValidationProfiles(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        return JsonResponse::ok(['geometry_validation_profiles' => $this->geometry->profiles($status)], $request->requestId());
    }

    public function printabilityRules(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        return JsonResponse::ok(['printability_rules' => $this->geometry->rules($status)], $request->requestId());
    }

    public function geometryAssets(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['geometry_assets' => $this->geometry->geometryAssets($status, $limit)], $request->requestId());
    }

    public function createGeometryAsset(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['geometry_asset' => $this->geometry->createGeometryAsset($request->body(), $user->id)], $request->requestId());
    }

    public function evaluateGeometryAsset(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['geometry_evaluation' => $this->geometry->evaluateGeometryAsset((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function geometryValidationRuns(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['geometry_validation_runs' => $this->geometry->validationRuns($status, $limit)], $request->requestId());
    }

    public function printabilityFindings(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'open');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['printability_findings' => $this->geometry->findings($status, $limit)], $request->requestId());
    }

    public function geometryReviewItems(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['geometry_review_items' => $this->geometry->reviews($status, $limit)], $request->requestId());
    }

    public function reviewGeometryItem(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['geometry_review_item' => $this->geometry->reviewGeometry((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function geometryGovernanceAuditLog(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['geometry_governance_audit_log' => $this->geometry->auditLog($limit)], $request->requestId());
    }


    public function providerRouting(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['provider_routing' => $this->routing->dashboard()], $request->requestId());
    }

    public function providerCapabilities(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['provider_capabilities' => $this->routing->providerCapabilities($status, $limit)], $request->requestId());
    }

    public function machineProfiles(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['machine_profiles' => $this->routing->machineProfiles($status, $limit)], $request->requestId());
    }

    public function routingPolicies(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        return JsonResponse::ok(['routing_policies' => $this->routing->routingPolicies($status)], $request->requestId());
    }

    public function routingRequests(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['routing_requests' => $this->routing->routingRequests($status, $limit)], $request->requestId());
    }

    public function createRoutingRequest(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['routing_request' => $this->routing->createRoutingRequest($request->body(), $user->id)], $request->requestId());
    }

    public function evaluateRoutingRequest(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created(['routing_evaluation' => $this->routing->evaluateRoutingRequest((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function routingMatches(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['routing_matches' => $this->routing->routingMatches($status, $limit)], $request->requestId());
    }

    public function routingReviews(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = (string) $request->query('status', 'active');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['routing_review_items' => $this->routing->routingReviews($status, $limit)], $request->requestId());
    }

    public function reviewRoutingItem(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['routing_review_item' => $this->routing->reviewRouting((string) $request->param('id'), $request->body(), $user->id)], $request->requestId());
    }

    public function providerRoutingAuditLog(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['provider_routing_audit_log' => $this->routing->auditLog($limit)], $request->requestId());
    }

}
