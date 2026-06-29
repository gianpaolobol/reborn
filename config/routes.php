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
