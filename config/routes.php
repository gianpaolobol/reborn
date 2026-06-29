<?php

declare(strict_types=1);

use Reborn\AI\Presentation\RecognitionJobController;
use Reborn\Dashboard\Presentation\DashboardController;
use Reborn\Fulfilment\Presentation\RepairFulfilmentController;
use Reborn\Governance\Presentation\GovernanceController;
use Reborn\Identity\Application\AuthContext;
use Reborn\Learning\Presentation\LearningController;
use Reborn\Identity\Domain\User;
use Reborn\Identity\Presentation\AuthController;
use Reborn\Marketplace\Presentation\RepairPathDecisionController;
use Reborn\Marketplace\Presentation\RepairOrderController;
use Reborn\Operations\Presentation\AdminOperationsController;
use Reborn\Platform\Presentation\PlatformController;
use Reborn\Provider\Presentation\ProviderMatchController;
use Reborn\Repair\Presentation\RepairController;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Http\Router;
use Reborn\Trust\Presentation\TrustController;

return static function (Router $router, RepairController $repairController, AuthController $authController, DashboardController $dashboardController, RecognitionJobController $recognitionJobController, RepairPathDecisionController $repairPathDecisionController, ProviderMatchController $providerMatchController, RepairOrderController $repairOrderController, RepairFulfilmentController $repairFulfilmentController, LearningController $learningController, TrustController $trustController, GovernanceController $governanceController, AdminOperationsController $adminOperationsController, PlatformController $platformController, AuthContext $auth, PDO $pdo): void {
    $router->get('/api/health', static function (Request $request): JsonResponse {
        return JsonResponse::ok([
            'status' => 'ok',
            'service' => 'Re-born Repair Intelligence API',
            'version' => 'v1',
            'mission' => 'Allow anyone to repair anything.',
            'capabilities' => [
                'repair_case_intake',
                'mock_diagnosis',
                'repair_paths',
                'provider_matching',
                'knowledge_nodes',
                'repair_attachments',
                'identity_access_mvp',
                'repair_case_ownership',
                'role_dashboards',
                'role_based_authorization',
                'repair_uploads',
                'ai_recognition_jobs',
                'repair_path_decision_engine',
                'provider_match_engine',
                'provider_quote_engine',
                'repair_order_engine',
                'mock_payment_intents',
                'repair_fulfilment_workflow',
                'provider_acceptance',
                'repair_completion_reports',
                'repair_learning_events',
                'knowledge_graph_feedback',
                'trust_reviews',
                'provider_quality_scoring',
                'provider_trust_signals',
                'provider_ranking_governance',
                'marketplace_governance_actions',
                'governance_audit',
                'admin_operations_console',
                'moderation_workflow',
                'ops_review_queue',
                'ops_escalations',
                'ops_audit_log',
                'production_readiness_hardening',
                'security_headers',
                'rate_limiting',
                'readiness_checks',
                'deploy_checklist',
                'observability_dashboard',
                'http_metrics',
                'api_log_viewer',
                'backup_automation',
                'deployment_runbook',
                'smoke_test_summary',
                'incident_response',
                'alert_rules',
                'status_page',
                'maintenance_windows',
                'notification_center',
                'notification_channels',
                'notification_dispatch',
                'escalation_policies',
                'escalation_runs',
                'service_governance',
                'sla_policies',
                'sla_evaluations',
                'operational_policies',
                'policy_attestations',
                'privacy_governance',
                'privacy_notices',
                'consent_records',
                'data_processing_records',
                'retention_rules',
                'data_subject_requests',
                'data_exports',
                'release_management',
                'feature_flags',
                'release_gates',
                'beta_readiness',
                'pilot_cohorts',
                'pilot_participants',
                'partner_onboarding',
                'partner_accounts',
                'partner_readiness',
                'partner_agreements',
                'partner_integrations',
                'marketplace_revenue_governance',
                'repair_credits',
                'credit_ledger',
                'payout_governance',
                'fee_policies',
                'maker_economy',
                'maker_profiles',
                'model_licensing',
                'model_download_governance',
                'maker_royalties',
                'repair_bounties',
                'bounty_submissions',
                'domain_events',
            ],
        ], $request->requestId());
    });

    $router->get('/api/ready', [$platformController, 'ready']);
    $router->get('/api/v1/platform/readiness', [$platformController, 'ready']);
    $router->get('/api/v1/platform/security-policy', [$platformController, 'securityPolicy']);
    $router->get('/api/v1/platform/deploy-checklist', [$platformController, 'deployChecklist']);
    $router->get('/api/v1/platform/runtime', [$platformController, 'runtime']);
    $router->post('/api/v1/platform/readiness-snapshots', [$platformController, 'storeReadinessSnapshot']);
    $router->get('/api/v1/platform/readiness-snapshots', [$platformController, 'readinessSnapshots']);
    $router->get('/api/v1/platform/observability', [$platformController, 'observability']);
    $router->get('/api/v1/platform/http-metrics', [$platformController, 'httpMetrics']);
    $router->get('/api/v1/platform/logs', [$platformController, 'logs']);
    $router->get('/api/v1/platform/backups', [$platformController, 'backups']);
    $router->post('/api/v1/platform/backups', [$platformController, 'createBackup']);
    $router->get('/api/v1/platform/deployment-runbook', [$platformController, 'deploymentRunbook']);
    $router->get('/api/v1/platform/smoke-tests-summary', [$platformController, 'smokeTestsSummary']);
    $router->get('/api/status', [$platformController, 'statusPage']);
    $router->get('/api/v1/platform/status-page', [$platformController, 'statusPage']);
    $router->get('/api/v1/platform/incident-response', [$platformController, 'incidentResponse']);
    $router->get('/api/v1/platform/alert-rules', [$platformController, 'alertRules']);
    $router->post('/api/v1/platform/alerts/evaluate', [$platformController, 'evaluateAlerts']);
    $router->get('/api/v1/platform/alerts', [$platformController, 'alerts']);
    $router->post('/api/v1/platform/alerts/{id}/acknowledge', [$platformController, 'acknowledgeAlert']);
    $router->post('/api/v1/platform/alerts/{id}/resolve', [$platformController, 'resolveAlert']);
    $router->get('/api/v1/platform/incidents', [$platformController, 'incidents']);
    $router->post('/api/v1/platform/incidents', [$platformController, 'createIncident']);
    $router->post('/api/v1/platform/incidents/{id}/status', [$platformController, 'updateIncidentStatus']);
    $router->get('/api/v1/platform/status-updates', [$platformController, 'statusUpdates']);
    $router->post('/api/v1/platform/status-updates', [$platformController, 'createStatusUpdate']);
    $router->get('/api/v1/platform/maintenance-windows', [$platformController, 'maintenanceWindows']);
    $router->post('/api/v1/platform/maintenance-windows', [$platformController, 'createMaintenanceWindow']);
    $router->post('/api/v1/platform/maintenance-windows/{id}/close', [$platformController, 'closeMaintenanceWindow']);
    $router->get('/api/v1/platform/notification-center', [$platformController, 'notificationCenter']);
    $router->get('/api/v1/platform/notification-channels', [$platformController, 'notificationChannels']);
    $router->post('/api/v1/platform/notification-channels', [$platformController, 'createNotificationChannel']);
    $router->get('/api/v1/platform/notification-rules', [$platformController, 'notificationRules']);
    $router->get('/api/v1/platform/notification-deliveries', [$platformController, 'notificationDeliveries']);
    $router->post('/api/v1/platform/notifications/dispatch', [$platformController, 'dispatchNotifications']);
    $router->post('/api/v1/platform/notification-deliveries/{id}/status', [$platformController, 'markNotificationDelivery']);
    $router->get('/api/v1/platform/escalation-policies', [$platformController, 'escalationPolicies']);
    $router->get('/api/v1/platform/escalation-runs', [$platformController, 'escalationRuns']);
    $router->post('/api/v1/platform/incidents/{id}/escalate', [$platformController, 'escalateIncident']);
    $router->get('/api/v1/platform/service-governance', [$platformController, 'serviceGovernance']);
    $router->get('/api/v1/platform/sla-policies', [$platformController, 'slaPolicies']);
    $router->post('/api/v1/platform/slas/evaluate', [$platformController, 'evaluateSlas']);
    $router->get('/api/v1/platform/sla-evaluations', [$platformController, 'slaEvaluations']);
    $router->post('/api/v1/platform/sla-evaluations/{id}/response', [$platformController, 'markSlaResponse']);
    $router->post('/api/v1/platform/sla-evaluations/{id}/resolve', [$platformController, 'markSlaResolved']);
    $router->get('/api/v1/platform/operational-policies', [$platformController, 'operationalPolicies']);
    $router->get('/api/v1/platform/policy-attestations', [$platformController, 'policyAttestations']);
    $router->post('/api/v1/platform/operational-policies/{id}/attest', [$platformController, 'attestOperationalPolicy']);

    $router->get('/api/v1/platform/privacy-governance', [$platformController, 'privacyGovernance']);
    $router->get('/api/v1/platform/privacy-notices', [$platformController, 'privacyNotices']);
    $router->get('/api/v1/platform/consent-records', [$platformController, 'consentRecords']);
    $router->post('/api/v1/platform/consent-records', [$platformController, 'recordConsent']);
    $router->post('/api/v1/platform/consent-records/{id}/withdraw', [$platformController, 'withdrawConsent']);
    $router->get('/api/v1/platform/data-processing-records', [$platformController, 'dataProcessingRecords']);
    $router->get('/api/v1/platform/retention-rules', [$platformController, 'retentionRules']);
    $router->post('/api/v1/platform/retention/evaluate', [$platformController, 'evaluateRetention']);
    $router->get('/api/v1/platform/retention-evaluations', [$platformController, 'retentionEvaluations']);
    $router->get('/api/v1/platform/data-subject-requests', [$platformController, 'dataSubjectRequests']);
    $router->post('/api/v1/platform/data-subject-requests', [$platformController, 'createDataSubjectRequest']);
    $router->post('/api/v1/platform/data-subject-requests/{id}/resolve', [$platformController, 'resolveDataSubjectRequest']);
    $router->post('/api/v1/platform/data-subject-requests/{id}/export', [$platformController, 'generateDataExport']);
    $router->get('/api/v1/platform/data-exports', [$platformController, 'dataExports']);

    $router->get('/api/v1/platform/release-management', [$platformController, 'releaseManagement']);
    $router->get('/api/v1/platform/beta-readiness', [$platformController, 'betaReadiness']);
    $router->get('/api/v1/platform/feature-flags', [$platformController, 'featureFlags']);
    $router->post('/api/v1/platform/feature-flags/{id}', [$platformController, 'updateFeatureFlag']);
    $router->get('/api/v1/platform/releases', [$platformController, 'releases']);
    $router->post('/api/v1/platform/releases', [$platformController, 'createRelease']);
    $router->post('/api/v1/platform/releases/{id}/evaluate-gates', [$platformController, 'evaluateReleaseGates']);
    $router->get('/api/v1/platform/releases/{id}/gates', [$platformController, 'releaseGates']);
    $router->post('/api/v1/platform/releases/{id}/decision', [$platformController, 'decideRelease']);
    $router->get('/api/v1/platform/release-decisions', [$platformController, 'releaseDecisions']);
    $router->get('/api/v1/platform/pilot-cohorts', [$platformController, 'pilotCohorts']);
    $router->post('/api/v1/platform/pilot-cohorts/{id}', [$platformController, 'updatePilotCohort']);
    $router->get('/api/v1/platform/pilot-participants', [$platformController, 'pilotParticipants']);
    $router->post('/api/v1/platform/pilot-participants', [$platformController, 'addPilotParticipant']);
    $router->post('/api/v1/platform/pilot-participants/{id}', [$platformController, 'updatePilotParticipant']);

    $router->get('/api/v1/platform/partner-onboarding', [$platformController, 'partnerOnboarding']);
    $router->get('/api/v1/platform/partners', [$platformController, 'partners']);
    $router->post('/api/v1/platform/partners', [$platformController, 'createPartner']);
    $router->get('/api/v1/platform/partners/{id}/readiness', [$platformController, 'partnerReadiness']);
    $router->post('/api/v1/platform/partners/{id}/readiness/evaluate', [$platformController, 'evaluatePartnerReadiness']);
    $router->get('/api/v1/platform/partner-tasks', [$platformController, 'partnerTasks']);
    $router->post('/api/v1/platform/partner-tasks/{id}/status', [$platformController, 'updatePartnerTaskStatus']);
    $router->get('/api/v1/platform/partner-agreements', [$platformController, 'partnerAgreements']);
    $router->post('/api/v1/platform/partners/{id}/agreements', [$platformController, 'createPartnerAgreement']);
    $router->post('/api/v1/platform/partner-agreements/{id}/status', [$platformController, 'updatePartnerAgreementStatus']);
    $router->get('/api/v1/platform/partner-integrations', [$platformController, 'partnerIntegrations']);
    $router->post('/api/v1/platform/partners/{id}/integrations', [$platformController, 'createPartnerIntegration']);
    $router->post('/api/v1/platform/partner-integrations/{id}/status', [$platformController, 'updatePartnerIntegrationStatus']);
    $router->get('/api/v1/platform/partner-readiness-reviews', [$platformController, 'partnerReadinessReviews']);

    $router->get('/api/v1/platform/marketplace-revenue', [$platformController, 'marketplaceRevenue']);
    $router->get('/api/v1/platform/marketplace-fee-policies', [$platformController, 'marketplaceFeePolicies']);
    $router->get('/api/v1/platform/credit-accounts', [$platformController, 'creditAccounts']);
    $router->post('/api/v1/platform/credit-accounts', [$platformController, 'createCreditAccount']);
    $router->get('/api/v1/platform/credit-transactions', [$platformController, 'creditTransactions']);
    $router->post('/api/v1/platform/credit-transactions', [$platformController, 'recordCreditTransaction']);
    $router->get('/api/v1/platform/payout-accounts', [$platformController, 'payoutAccounts']);
    $router->post('/api/v1/platform/payout-accounts', [$platformController, 'createPayoutAccount']);
    $router->get('/api/v1/platform/payout-runs', [$platformController, 'payoutRuns']);
    $router->post('/api/v1/platform/payout-runs/evaluate', [$platformController, 'evaluatePayoutRun']);
    $router->post('/api/v1/platform/payout-runs/{id}/approve', [$platformController, 'approvePayoutRun']);
    $router->post('/api/v1/platform/payout-runs/{id}/paid', [$platformController, 'markPayoutRunPaid']);
    $router->get('/api/v1/platform/payout-items', [$platformController, 'payoutItems']);
    $router->get('/api/v1/platform/revenue-audit-log', [$platformController, 'revenueAuditLog']);

    $router->get('/api/v1/platform/maker-economy', [$platformController, 'makerEconomy']);
    $router->get('/api/v1/platform/maker-profiles', [$platformController, 'makerProfiles']);
    $router->post('/api/v1/platform/maker-profiles', [$platformController, 'createMakerProfile']);
    $router->post('/api/v1/platform/maker-profiles/{id}/status', [$platformController, 'updateMakerProfileStatus']);
    $router->get('/api/v1/platform/model-assets', [$platformController, 'modelAssets']);
    $router->post('/api/v1/platform/model-assets', [$platformController, 'submitModelAsset']);
    $router->post('/api/v1/platform/model-assets/{id}/review', [$platformController, 'reviewModelAsset']);
    $router->get('/api/v1/platform/model-licenses', [$platformController, 'modelLicenses']);
    $router->get('/api/v1/platform/model-downloads', [$platformController, 'modelDownloads']);
    $router->post('/api/v1/platform/model-downloads', [$platformController, 'recordModelDownload']);
    $router->get('/api/v1/platform/model-royalty-events', [$platformController, 'modelRoyaltyEvents']);
    $router->get('/api/v1/platform/repair-bounties', [$platformController, 'repairBounties']);
    $router->post('/api/v1/platform/repair-bounties', [$platformController, 'createRepairBounty']);
    $router->get('/api/v1/platform/bounty-submissions', [$platformController, 'bountySubmissions']);
    $router->post('/api/v1/platform/bounty-submissions', [$platformController, 'submitBounty']);
    $router->post('/api/v1/platform/bounty-submissions/{id}/review', [$platformController, 'reviewBountySubmission']);
    $router->get('/api/v1/platform/maker-economy-audit-log', [$platformController, 'makerEconomyAuditLog']);

    $router->post('/api/v1/auth/register', [$authController, 'register']);
    $router->post('/api/v1/auth/login', [$authController, 'login']);
    $router->get('/api/v1/auth/me', [$authController, 'me']);
    $router->post('/api/v1/auth/logout', [$authController, 'logout']);

    $router->get('/api/v1/dashboard', [$dashboardController, 'me']);
    $router->get('/api/v1/dashboards/repair-user', [$dashboardController, 'repairUser']);
    $router->get('/api/v1/dashboards/maker', [$dashboardController, 'maker']);
    $router->get('/api/v1/dashboards/provider', [$dashboardController, 'provider']);
    $router->get('/api/v1/dashboards/enterprise', [$dashboardController, 'enterprise']);
    $router->get('/api/v1/dashboards/admin', [$dashboardController, 'admin']);

    $router->get('/api/v1/repair-cases', [$repairController, 'index']);
    $router->post('/api/v1/repair-cases', [$repairController, 'store']);
    $router->get('/api/v1/repair-cases/{id}', [$repairController, 'show']);
    $router->post('/api/v1/repair-cases/{id}/diagnose', [$repairController, 'diagnose']);
    $router->get('/api/v1/repair-cases/{id}/attachments', [$repairController, 'attachments']);
    $router->post('/api/v1/repair-cases/{id}/attachments', [$repairController, 'attach']);
    $router->get('/api/v1/repair-cases/{id}/recognition-jobs', [$recognitionJobController, 'index']);
    $router->post('/api/v1/repair-cases/{id}/recognition-jobs', [$recognitionJobController, 'store']);
    $router->get('/api/v1/recognition-jobs/{id}', [$recognitionJobController, 'show']);
    $router->get('/api/v1/repair-cases/{id}/repair-path-decisions', [$repairPathDecisionController, 'index']);
    $router->post('/api/v1/repair-cases/{id}/repair-path-decisions', [$repairPathDecisionController, 'store']);
    $router->get('/api/v1/repair-path-decisions/{id}', [$repairPathDecisionController, 'show']);
    $router->get('/api/v1/repair-cases/{id}/provider-matches', [$providerMatchController, 'index']);
    $router->post('/api/v1/repair-cases/{id}/provider-matches', [$providerMatchController, 'store']);
    $router->get('/api/v1/provider-matches/{id}', [$providerMatchController, 'show']);
    $router->post('/api/v1/provider-matches/{id}/quote-requests', [$providerMatchController, 'storeQuote']);
    $router->get('/api/v1/repair-cases/{id}/quote-requests', [$providerMatchController, 'quotes']);
    $router->get('/api/v1/quote-requests/{id}', [$providerMatchController, 'showQuote']);
    $router->post('/api/v1/quote-requests/{id}/repair-orders', [$repairOrderController, 'storeFromQuote']);
    $router->get('/api/v1/repair-cases/{id}/repair-orders', [$repairOrderController, 'indexForCase']);
    $router->get('/api/v1/repair-orders/{id}', [$repairOrderController, 'show']);
    $router->post('/api/v1/repair-orders/{id}/payment-intents', [$repairOrderController, 'storePaymentIntent']);
    $router->get('/api/v1/repair-orders/{id}/payment-intents', [$repairOrderController, 'paymentIntents']);
    $router->get('/api/v1/payment-intents/{id}', [$repairOrderController, 'showPaymentIntent']);
    $router->post('/api/v1/payment-intents/{id}/confirm-mock', [$repairOrderController, 'confirmMockPaymentIntent']);

    $router->post('/api/v1/repair-orders/{id}/fulfilments', [$repairFulfilmentController, 'storeForOrder']);
    $router->get('/api/v1/repair-orders/{id}/fulfilments', [$repairFulfilmentController, 'indexForOrder']);
    $router->get('/api/v1/fulfilments/{id}', [$repairFulfilmentController, 'show']);
    $router->post('/api/v1/fulfilments/{id}/accept-provider', [$repairFulfilmentController, 'acceptProvider']);
    $router->post('/api/v1/fulfilments/{id}/status', [$repairFulfilmentController, 'updateStatus']);
    $router->get('/api/v1/fulfilments/{id}/completion-reports', [$learningController, 'completionReportsForFulfilment']);
    $router->post('/api/v1/fulfilments/{id}/completion-reports', [$learningController, 'storeCompletionReport']);
    $router->get('/api/v1/completion-reports/{id}', [$learningController, 'showCompletionReport']);
    $router->get('/api/v1/repair-cases/{id}/learning-events', [$learningController, 'learningEventsForCase']);
    $router->get('/api/v1/learning-events/{id}', [$learningController, 'showLearningEvent']);

    $router->post('/api/v1/completion-reports/{id}/trust-reviews', [$trustController, 'storeReviewForCompletionReport']);
    $router->get('/api/v1/completion-reports/{id}/trust-reviews', [$trustController, 'reviewsForCompletionReport']);
    $router->get('/api/v1/provider-quality-scores', [$trustController, 'providerQualityScores']);
    $router->get('/api/v1/providers/{id}/quality-score', [$trustController, 'providerQualityScore']);
    $router->get('/api/v1/providers/{id}/trust-signals', [$trustController, 'providerTrustSignals']);
    $router->get('/api/v1/providers/{id}/trust-reviews', [$trustController, 'providerTrustReviews']);
    $router->post('/api/v1/governance/ranking-snapshots', [$governanceController, 'createRankingSnapshot']);
    $router->get('/api/v1/governance/ranking-snapshots/latest', [$governanceController, 'latestRankingSnapshot']);
    $router->get('/api/v1/governance/provider-rankings', [$governanceController, 'providerRankings']);
    $router->post('/api/v1/providers/{id}/governance-actions', [$governanceController, 'recordProviderAction']);
    $router->get('/api/v1/providers/{id}/governance-actions', [$governanceController, 'providerActions']);
    $router->get('/api/v1/governance/actions', [$governanceController, 'actions']);
    $router->get('/api/v1/governance/summary', [$governanceController, 'summary']);
    $router->get('/api/v1/governance/policies', [$governanceController, 'policies']);

    $router->post('/api/v1/ops/review-items', [$adminOperationsController, 'createReviewItem']);
    $router->get('/api/v1/ops/review-items', [$adminOperationsController, 'reviewItems']);
    $router->get('/api/v1/ops/review-items/{id}', [$adminOperationsController, 'reviewItem']);
    $router->post('/api/v1/ops/review-items/{id}/assign', [$adminOperationsController, 'assignReviewItem']);
    $router->post('/api/v1/ops/review-items/{id}/moderation-actions', [$adminOperationsController, 'recordModerationAction']);
    $router->post('/api/v1/ops/review-items/{id}/escalations', [$adminOperationsController, 'createEscalation']);
    $router->post('/api/v1/ops/review-items/{id}/resolve', [$adminOperationsController, 'resolveReviewItem']);
    $router->get('/api/v1/ops/escalations', [$adminOperationsController, 'escalations']);
    $router->get('/api/v1/ops/summary', [$adminOperationsController, 'summary']);
    $router->get('/api/v1/ops/policies', [$adminOperationsController, 'policies']);

    $router->get('/api/v1/providers', static function (Request $request) use ($pdo): JsonResponse {
        $stmt = $pdo->query('SELECT id, name, city, country, capabilities, rating, average_lead_time_days FROM providers ORDER BY rating DESC, name ASC');
        return JsonResponse::ok(['providers' => array_map(static function (array $row): array {
            $row['capabilities'] = json_decode($row['capabilities'] ?: '[]', true);
            $row['rating'] = (float) $row['rating'];
            $row['average_lead_time_days'] = (int) $row['average_lead_time_days'];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))], $request->requestId());
    });

    $router->get('/api/v1/knowledge/nodes', static function (Request $request) use ($pdo): JsonResponse {
        $stmt = $pdo->query('SELECT id, type, label, confidence_score, metadata FROM knowledge_nodes ORDER BY confidence_score DESC, label ASC');
        return JsonResponse::ok(['nodes' => array_map(static function (array $row): array {
            $row['confidence_score'] = (float) $row['confidence_score'];
            $row['metadata'] = json_decode($row['metadata'] ?: '{}', true);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))], $request->requestId());
    });

    $router->get('/api/v1/repair-paths', static function (Request $request) use ($pdo): JsonResponse {
        $caseId = $request->query('case_id');
        if (!$caseId) {
            return JsonResponse::validation(['case_id' => ['case_id is required.']], $request->requestId());
        }

        $stmt = $pdo->prepare('SELECT id, repair_case_id, type, title, description, confidence_score, estimated_price_cents, estimated_days FROM repair_paths WHERE repair_case_id = :case_id ORDER BY confidence_score DESC');
        $stmt->execute(['case_id' => $caseId]);

        return JsonResponse::ok(['repair_paths' => array_map(static function (array $row): array {
            $row['confidence_score'] = (float) $row['confidence_score'];
            $row['estimated_price_cents'] = (int) $row['estimated_price_cents'];
            $row['estimated_days'] = (int) $row['estimated_days'];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))], $request->requestId());
    });

    $router->get('/api/v1/domain-events', static function (Request $request) use ($pdo, $auth): JsonResponse {
        $auth->requireRole($request, [User::ROLE_ADMIN]);
        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        $stmt = $pdo->prepare('SELECT id, name, payload, occurred_at FROM domain_events ORDER BY occurred_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return JsonResponse::ok(['domain_events' => array_map(static function (array $row): array {
            $row['payload'] = json_decode($row['payload'] ?: '{}', true);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))], $request->requestId());
    });

    $router->get('/api/v1/admin/users', static function (Request $request) use ($pdo, $auth): JsonResponse {
        $auth->requireRole($request, [User::ROLE_ADMIN]);
        $stmt = $pdo->query('SELECT id, email, name, role, status, email_verified_at, created_at, updated_at, last_login_at FROM users ORDER BY created_at DESC');
        return JsonResponse::ok(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)], $request->requestId());
    });
};
