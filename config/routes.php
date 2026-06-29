<?php

declare(strict_types=1);

use Reborn\Repair\Presentation\RepairController;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Http\Router;

return static function (Router $router, RepairController $repairController, PDO $pdo): void {
    $router->get('/api/health', static function (): JsonResponse {
        return JsonResponse::ok([
            'status' => 'ok',
            'service' => 'Re-born Repair Intelligence API',
            'version' => 'v1',
            'mission' => 'Allow anyone to repair anything.',
        ]);
    });

    $router->get('/api/v1/repair-cases', [$repairController, 'index']);
    $router->post('/api/v1/repair-cases', [$repairController, 'store']);
    $router->get('/api/v1/repair-cases/{id}', [$repairController, 'show']);
    $router->post('/api/v1/repair-cases/{id}/diagnose', [$repairController, 'diagnose']);

    $router->get('/api/v1/providers', static function (Request $request) use ($pdo): JsonResponse {
        $stmt = $pdo->query('SELECT id, name, city, country, capabilities, rating, average_lead_time_days FROM providers ORDER BY rating DESC, name ASC');
        return JsonResponse::ok(['providers' => array_map(static function (array $row): array {
            $row['capabilities'] = json_decode($row['capabilities'] ?: '[]', true);
            $row['rating'] = (float) $row['rating'];
            $row['average_lead_time_days'] = (int) $row['average_lead_time_days'];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))]);
    });

    $router->get('/api/v1/knowledge/nodes', static function () use ($pdo): JsonResponse {
        $stmt = $pdo->query('SELECT id, type, label, confidence_score, metadata FROM knowledge_nodes ORDER BY confidence_score DESC, label ASC');
        return JsonResponse::ok(['nodes' => array_map(static function (array $row): array {
            $row['confidence_score'] = (float) $row['confidence_score'];
            $row['metadata'] = json_decode($row['metadata'] ?: '{}', true);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))]);
    });

    $router->get('/api/v1/repair-paths', static function (Request $request) use ($pdo): JsonResponse {
        $caseId = $request->query('case_id');
        if (!$caseId) {
            return JsonResponse::validation(['case_id' => ['case_id is required.']]);
        }

        $stmt = $pdo->prepare('SELECT id, repair_case_id, type, title, description, confidence_score, estimated_price_cents, estimated_days FROM repair_paths WHERE repair_case_id = :case_id ORDER BY confidence_score DESC');
        $stmt->execute(['case_id' => $caseId]);

        return JsonResponse::ok(['repair_paths' => array_map(static function (array $row): array {
            $row['confidence_score'] = (float) $row['confidence_score'];
            $row['estimated_price_cents'] = (int) $row['estimated_price_cents'];
            $row['estimated_days'] = (int) $row['estimated_days'];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))]);
    });
};
