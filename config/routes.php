<?php

declare(strict_types=1);

use Reborn\AI\Presentation\RecognitionJobController;
use Reborn\Dashboard\Presentation\DashboardController;
use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Identity\Presentation\AuthController;
use Reborn\Marketplace\Presentation\RepairPathDecisionController;
use Reborn\Provider\Presentation\ProviderMatchController;
use Reborn\Repair\Presentation\RepairController;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Http\Router;

return static function (Router $router, RepairController $repairController, AuthController $authController, DashboardController $dashboardController, RecognitionJobController $recognitionJobController, RepairPathDecisionController $repairPathDecisionController, ProviderMatchController $providerMatchController, AuthContext $auth, PDO $pdo): void {
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
                'domain_events',
            ],
        ], $request->requestId());
    });

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
