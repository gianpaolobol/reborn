<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class ProviderRoutingGovernanceService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'summary' => [
                'provider_capabilities' => $this->count('platform_provider_capability_profiles'),
                'active_machines' => $this->count('platform_machine_profiles', "status = 'active'"),
                'routing_policies' => $this->count('platform_routing_policies', "status = 'active'"),
                'routing_requests' => $this->count('platform_fulfilment_routing_requests'),
                'candidate_matches' => $this->count('platform_provider_routing_matches'),
                'open_reviews' => $this->count('platform_routing_review_items', "status IN ('open','assigned')"),
            ],
            'latest_requests' => $this->routingRequests('all', 5),
            'top_matches' => $this->routingMatches('all', 5),
            'open_reviews' => $this->routingReviews('active', 5),
            'policies' => $this->routingPolicies('active'),
            'scope_note' => 'Step 33 uses provider/machine capability governance and mock routing. It does not reserve real capacity or create real shipment fulfilment.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function providerCapabilities(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_provider_capability_profiles';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY quality_score DESC, locality_score DESC, provider_name ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeCapability'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function machineProfiles(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT m.*, c.provider_id, c.provider_name FROM platform_machine_profiles m JOIN platform_provider_capability_profiles c ON c.id = m.provider_capability_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE m.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY m.reliability_score DESC, m.machine_name ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeMachine'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function routingPolicies(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_routing_policies';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY priority ASC, name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(static function (array $row): array {
            $row['priority'] = (int) $row['priority'];
            $row['rules'] = json_decode($row['rules_json'] ?: '{}', true) ?: [];
            unset($row['rules_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function routingRequests(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*, a.asset_code, a.file_name, a.status AS geometry_status, a.bounding_box_mm, a.estimated_volume_cm3 FROM platform_fulfilment_routing_requests r LEFT JOIN platform_geometry_assets a ON a.id = r.geometry_asset_id';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE r.status IN ('draft','evaluated','matched','needs_review')";
            } else {
                $sql .= ' WHERE r.status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeRoutingRequest'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createRoutingRequest(array $body, ?string $userId): array
    {
        $geometryAssetId = trim((string) ($body['geometry_asset_id'] ?? '')) ?: null;
        if ($geometryAssetId !== null) {
            $this->requireGeometryAsset($geometryAssetId);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $requestCode = 'ROUTE-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $stmt = $this->pdo->prepare('INSERT INTO platform_fulfilment_routing_requests (id, request_code, geometry_asset_id, repair_case_id, requested_process, material_family, quantity, priority, destination_country, max_lead_time_days, max_budget_cents, status, decision, routing_context_json, created_by, created_at, updated_at) VALUES (:id, :request_code, :geometry_asset_id, :repair_case_id, :requested_process, :material_family, :quantity, :priority, :destination_country, :max_lead_time_days, :max_budget_cents, :status, :decision, :routing_context_json, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'request_code' => $requestCode,
            'geometry_asset_id' => $geometryAssetId,
            'repair_case_id' => trim((string) ($body['repair_case_id'] ?? '')) ?: null,
            'requested_process' => trim((string) ($body['requested_process'] ?? 'fdm_3d_printing')) ?: 'fdm_3d_printing',
            'material_family' => trim((string) ($body['material_family'] ?? 'pla_petg')) ?: 'pla_petg',
            'quantity' => max(1, (int) ($body['quantity'] ?? 1)),
            'priority' => trim((string) ($body['priority'] ?? 'normal')) ?: 'normal',
            'destination_country' => strtoupper(trim((string) ($body['destination_country'] ?? 'IT'))) ?: 'IT',
            'max_lead_time_days' => max(1, (int) ($body['max_lead_time_days'] ?? 7)),
            'max_budget_cents' => max(100, (int) ($body['max_budget_cents'] ?? 4000)),
            'status' => 'draft',
            'decision' => null,
            'routing_context_json' => json_encode($body['routing_context'] ?? ['created_from' => 'step33_console'], JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('routing_request_created', 'routing_request', $id, sprintf('Routing request %s created.', $requestCode), ['geometry_asset_id' => $geometryAssetId], $userId);
        return $this->requireRoutingRequest($id);
    }

    /** @return array<string, mixed> */
    public function evaluateRoutingRequest(string $id, array $body, ?string $userId): array
    {
        $request = $this->requireRoutingRequest($id);
        $asset = $request['geometry_asset_id'] ? $this->requireGeometryAsset((string) $request['geometry_asset_id']) : null;
        $bbox = $asset ? $this->decodeJson($asset['bounding_box_mm'] ?? '{}') : ['x' => 80, 'y' => 40, 'z' => 20];
        $volume = $asset ? (float) ($asset['estimated_volume_cm3'] ?? 8.0) : (float) ($body['estimated_volume_cm3'] ?? 8.0);
        $geometryStatus = $asset ? (string) ($asset['status'] ?? 'unknown') : 'no_geometry_asset';

        $this->pdo->prepare('DELETE FROM platform_provider_routing_matches WHERE routing_request_id = :id')->execute(['id' => $id]);

        $candidates = $this->activeMachineCandidates((string) $request['requested_process'], (string) $request['material_family']);
        $matches = [];
        foreach ($candidates as $candidate) {
            $machineBox = $candidate['build_volume_mm'];
            $providerBox = $candidate['max_build_volume_mm'];
            $fitsMachine = $this->fitsBox($bbox, $machineBox);
            $fitsProvider = $this->fitsBox($bbox, $providerBox);
            $materialMatch = in_array((string) $request['material_family'], $candidate['machine_materials'], true) || in_array((string) $request['material_family'], $candidate['provider_materials'], true);
            $processMatch = (string) $request['requested_process'] === (string) $candidate['machine_process'];
            $leadTime = max(1, (int) $candidate['average_lead_time_days'] + ((string) $request['priority'] === 'urgent' ? -1 : 0));
            $cost = (int) $candidate['base_setup_fee_cents'] + (int) ceil(max(1.0, $volume) * (int) $candidate['price_per_cm3_cents']) * max(1, (int) $request['quantity']);
            $withinBudget = $cost <= (int) $request['max_budget_cents'];
            $withinLeadTime = $leadTime <= (int) $request['max_lead_time_days'];
            $geometryReady = in_array($geometryStatus, ['validated', 'reviewed_approved', 'reviewed_with_notes'], true);

            $score = 30;
            $score += $processMatch ? 12 : -25;
            $score += $materialMatch ? 15 : -25;
            $score += ($fitsMachine && $fitsProvider) ? 18 : -40;
            $score += $withinLeadTime ? 10 : -8;
            $score += $withinBudget ? 8 : -10;
            $score += (int) round(((float) $candidate['quality_score']) * 0.08);
            $score += (int) round(((float) $candidate['reliability_score']) * 0.06);
            $score += $geometryReady ? 4 : -6;
            $score = max(0, min(100, $score));

            $risks = [];
            if (!$fitsMachine || !$fitsProvider) { $risks[] = 'build_volume_exceeded'; }
            if (!$materialMatch) { $risks[] = 'material_not_supported'; }
            if (!$withinBudget) { $risks[] = 'above_budget'; }
            if (!$withinLeadTime) { $risks[] = 'lead_time_above_target'; }
            if (!$geometryReady) { $risks[] = 'geometry_not_fully_released'; }

            $matchId = Uuid::v4();
            $matches[] = [
                'id' => $matchId,
                'score' => $score,
                'candidate' => $candidate,
                'lead_time' => $leadTime,
                'cost' => $cost,
                'checks' => [
                    'process_match' => $processMatch,
                    'material_match' => $materialMatch,
                    'fits_machine' => $fitsMachine,
                    'fits_provider' => $fitsProvider,
                    'within_budget' => $withinBudget,
                    'within_lead_time' => $withinLeadTime,
                    'geometry_ready' => $geometryReady,
                    'bounding_box_mm' => $bbox,
                    'machine_build_volume_mm' => $machineBox,
                ],
                'reasons' => [
                    sprintf('%s on %s', $candidate['provider_name'], $candidate['machine_name']),
                    sprintf('Score %d based on machine fit, material, lead time, budget and quality signals.', $score),
                ],
                'risks' => $risks,
            ];
        }

        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $rank = 1;
        foreach ($matches as $match) {
            $status = $rank === 1 && $match['score'] >= 70 ? 'recommended' : 'candidate';
            $stmt = $this->pdo->prepare('INSERT INTO platform_provider_routing_matches (id, routing_request_id, provider_capability_id, machine_profile_id, status, rank, match_score, estimated_lead_time_days, estimated_cost_cents, currency, fit_checks_json, match_reasons_json, risks_json, created_at, updated_at) VALUES (:id, :routing_request_id, :provider_capability_id, :machine_profile_id, :status, :rank, :match_score, :estimated_lead_time_days, :estimated_cost_cents, :currency, :fit_checks_json, :match_reasons_json, :risks_json, :created_at, :updated_at)');
            $stmt->execute([
                'id' => $match['id'],
                'routing_request_id' => $id,
                'provider_capability_id' => $match['candidate']['provider_capability_id'],
                'machine_profile_id' => $match['candidate']['machine_profile_id'],
                'status' => $status,
                'rank' => $rank,
                'match_score' => $match['score'],
                'estimated_lead_time_days' => $match['lead_time'],
                'estimated_cost_cents' => $match['cost'],
                'currency' => 'EUR',
                'fit_checks_json' => json_encode($match['checks'], JSON_THROW_ON_ERROR),
                'match_reasons_json' => json_encode($match['reasons'], JSON_THROW_ON_ERROR),
                'risks_json' => json_encode($match['risks'], JSON_THROW_ON_ERROR),
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ]);
            $rank++;
        }

        $bestScore = $matches[0]['score'] ?? 0;
        $decision = $bestScore >= 70 ? 'route_recommended' : ($bestScore > 0 ? 'manual_review_required' : 'no_provider_match');
        $status = $decision === 'route_recommended' ? 'matched' : 'needs_review';
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_fulfilment_routing_requests SET status = :status, decision = :decision, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $status, 'decision' => $decision, 'updated_at' => $now, 'id' => $id]);

        $review = null;
        if ($decision !== 'route_recommended' || !in_array($geometryStatus, ['validated', 'reviewed_approved', 'reviewed_with_notes'], true)) {
            $review = $this->createReview($id, $decision === 'route_recommended' ? 'medium' : 'high', 'Routing requires operator confirmation before fulfilment handoff.', $userId);
        }

        $this->audit('routing_request_evaluated', 'routing_request', $id, sprintf('Routing request evaluated with decision %s and best score %d.', $decision, $bestScore), ['matches' => count($matches), 'geometry_status' => $geometryStatus], $userId);

        return [
            'routing_request' => $this->requireRoutingRequest($id),
            'matches' => $this->routingMatchesForRequest($id),
            'review_item' => $review,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function routingMatches(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = $this->matchSelectSql();
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE m.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY m.created_at DESC, m.rank ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeMatch'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function routingReviews(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT rv.*, rr.request_code, rr.status AS routing_status, rr.decision AS routing_decision FROM platform_routing_review_items rv JOIN platform_fulfilment_routing_requests rr ON rr.id = rv.routing_request_id';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE rv.status IN ('open','assigned')";
            } else {
                $sql .= ' WHERE rv.status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY rv.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function reviewRouting(string $id, array $body, ?string $userId): array
    {
        $review = $this->requireReview($id);
        $decision = trim((string) ($body['decision'] ?? 'approved_for_fulfilment')) ?: 'approved_for_fulfilment';
        $allowed = ['approved_for_fulfilment', 'approved_with_operator_notes', 'reroute_required', 'blocked'];
        if (!in_array($decision, $allowed, true)) {
            throw new ValidationException(['decision' => ['Unsupported routing review decision.']]);
        }
        $notes = trim((string) ($body['notes'] ?? 'Routing review completed.')) ?: 'Routing review completed.';
        $requestStatus = in_array($decision, ['approved_for_fulfilment', 'approved_with_operator_notes'], true) ? 'approved_for_fulfilment' : ($decision === 'blocked' ? 'blocked' : 'needs_review');
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_routing_review_items SET status = :status, decision = :decision, notes = :notes, reviewed_by = :reviewed_by, updated_at = :updated_at, reviewed_at = :reviewed_at WHERE id = :id');
        $stmt->execute(['status' => 'closed', 'decision' => $decision, 'notes' => $notes, 'reviewed_by' => $userId, 'updated_at' => $now, 'reviewed_at' => $now, 'id' => $id]);
        $stmt = $this->pdo->prepare('UPDATE platform_fulfilment_routing_requests SET status = :status, decision = :decision, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $requestStatus, 'decision' => $decision, 'updated_at' => $now, 'id' => $review['routing_request_id']]);
        $this->audit('routing_review_completed', 'routing_review_item', $id, sprintf('Routing review completed with decision %s.', $decision), ['routing_request_status' => $requestStatus], $userId);
        return $this->requireReview($id);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_provider_routing_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static function (array $row): array {
            $row['metadata'] = json_decode($row['metadata_json'] ?: '{}', true) ?: [];
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function requireRoutingRequest(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, a.asset_code, a.file_name, a.status AS geometry_status, a.bounding_box_mm, a.estimated_volume_cm3 FROM platform_fulfilment_routing_requests r LEFT JOIN platform_geometry_assets a ON a.id = r.geometry_asset_id WHERE r.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['routing_request_id' => ['Routing request not found.']]);
        }
        return $this->normalizeRoutingRequest($row);
    }

    /** @return array<string, mixed> */
    private function requireGeometryAsset(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_geometry_assets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['geometry_asset_id' => ['Geometry asset not found.']]);
        }
        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function activeMachineCandidates(string $process, string $materialFamily): array
    {
        $stmt = $this->pdo->prepare("SELECT c.id AS provider_capability_id, c.provider_id, c.provider_name, c.process AS provider_process, c.materials_json AS provider_materials_json, c.max_build_volume_mm, c.average_lead_time_days, c.base_setup_fee_cents, c.price_per_cm3_cents, c.quality_score, c.locality_score, m.id AS machine_profile_id, m.machine_code, m.machine_name, m.process AS machine_process, m.materials_json AS machine_materials_json, m.build_volume_mm, m.reliability_score FROM platform_provider_capability_profiles c JOIN platform_machine_profiles m ON m.provider_capability_id = c.id WHERE c.status = 'active' AND m.status = 'active' AND c.process = :process AND m.process = :process");
        $stmt->execute(['process' => $process]);
        return array_map(function (array $row) use ($materialFamily): array {
            $row['provider_materials'] = $this->decodeJson($row['provider_materials_json'] ?: '[]');
            $row['machine_materials'] = $this->decodeJson($row['machine_materials_json'] ?: '[]');
            $row['max_build_volume_mm'] = $this->decodeJson($row['max_build_volume_mm'] ?: '{}');
            $row['build_volume_mm'] = $this->decodeJson($row['build_volume_mm'] ?: '{}');
            $row['material_requested'] = $materialFamily;
            unset($row['provider_materials_json'], $row['machine_materials_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    private function routingMatchesForRequest(string $requestId): array
    {
        $stmt = $this->pdo->prepare($this->matchSelectSql() . ' WHERE m.routing_request_id = :routing_request_id ORDER BY m.rank ASC');
        $stmt->execute(['routing_request_id' => $requestId]);
        return array_map([$this, 'normalizeMatch'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function matchSelectSql(): string
    {
        return 'SELECT m.*, rr.request_code, c.provider_id, c.provider_name, mp.machine_code, mp.machine_name FROM platform_provider_routing_matches m JOIN platform_fulfilment_routing_requests rr ON rr.id = m.routing_request_id JOIN platform_provider_capability_profiles c ON c.id = m.provider_capability_id LEFT JOIN platform_machine_profiles mp ON mp.id = m.machine_profile_id';
    }

    /** @return array<string, mixed> */
    private function createReview(string $routingRequestId, string $priority, string $reason, ?string $userId): array
    {
        $existing = $this->pdo->prepare("SELECT id FROM platform_routing_review_items WHERE routing_request_id = :routing_request_id AND status IN ('open','assigned') ORDER BY created_at DESC LIMIT 1");
        $existing->execute(['routing_request_id' => $routingRequestId]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            return $this->requireReview((string) $existingId);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_routing_review_items (id, routing_request_id, status, priority, review_reason, assigned_to, decision, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :routing_request_id, :status, :priority, :review_reason, :assigned_to, :decision, :notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'routing_request_id' => $routingRequestId,
            'status' => 'open',
            'priority' => $priority,
            'review_reason' => $reason,
            'assigned_to' => null,
            'decision' => null,
            'notes' => 'Review generated by Step 33 routing governance.',
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        return $this->requireReview($id);
    }

    /** @return array<string, mixed> */
    private function requireReview(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT rv.*, rr.request_code, rr.status AS routing_status, rr.decision AS routing_decision FROM platform_routing_review_items rv JOIN platform_fulfilment_routing_requests rr ON rr.id = rv.routing_request_id WHERE rv.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['routing_review_id' => ['Routing review item not found.']]);
        }
        return $row;
    }

    private function fitsBox(array $box, array $limit): bool
    {
        return ((float) ($box['x'] ?? 0) <= (float) ($limit['x'] ?? 0)) && ((float) ($box['y'] ?? 0) <= (float) ($limit['y'] ?? 0)) && ((float) ($box['z'] ?? 0) <= (float) ($limit['z'] ?? 0));
    }

    /** @return array<string, mixed>|list<mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function normalizeCapability(array $row): array
    {
        $row['materials'] = $this->decodeJson($row['materials_json'] ?? '[]');
        $row['capabilities'] = $this->decodeJson($row['capabilities_json'] ?? '{}');
        $row['max_build_volume_mm'] = $this->decodeJson($row['max_build_volume_mm'] ?? '{}');
        $row['average_lead_time_days'] = (int) $row['average_lead_time_days'];
        $row['base_setup_fee_cents'] = (int) $row['base_setup_fee_cents'];
        $row['price_per_cm3_cents'] = (int) $row['price_per_cm3_cents'];
        $row['quality_score'] = (float) $row['quality_score'];
        $row['locality_score'] = (float) $row['locality_score'];
        unset($row['materials_json'], $row['capabilities_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeMachine(array $row): array
    {
        $row['materials'] = $this->decodeJson($row['materials_json'] ?? '[]');
        $row['build_volume_mm'] = $this->decodeJson($row['build_volume_mm'] ?? '{}');
        $row['nozzle_diameter_mm'] = (float) $row['nozzle_diameter_mm'];
        $row['min_layer_height_mm'] = (float) $row['min_layer_height_mm'];
        $row['max_layer_height_mm'] = (float) $row['max_layer_height_mm'];
        $row['reliability_score'] = (float) $row['reliability_score'];
        unset($row['materials_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeRoutingRequest(array $row): array
    {
        $row['quantity'] = (int) $row['quantity'];
        $row['max_lead_time_days'] = (int) $row['max_lead_time_days'];
        $row['max_budget_cents'] = (int) $row['max_budget_cents'];
        $row['routing_context'] = $this->decodeJson($row['routing_context_json'] ?? '{}');
        if (isset($row['bounding_box_mm'])) {
            $row['bounding_box_mm'] = $this->decodeJson($row['bounding_box_mm']);
        }
        if (isset($row['estimated_volume_cm3'])) {
            $row['estimated_volume_cm3'] = (float) $row['estimated_volume_cm3'];
        }
        unset($row['routing_context_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeMatch(array $row): array
    {
        $row['rank'] = (int) $row['rank'];
        $row['match_score'] = (int) $row['match_score'];
        $row['estimated_lead_time_days'] = (int) $row['estimated_lead_time_days'];
        $row['estimated_cost_cents'] = (int) $row['estimated_cost_cents'];
        $row['fit_checks'] = $this->decodeJson($row['fit_checks_json'] ?? '{}');
        $row['match_reasons'] = $this->decodeJson($row['match_reasons_json'] ?? '[]');
        $row['risks'] = $this->decodeJson($row['risks_json'] ?? '[]');
        unset($row['fit_checks_json'], $row['match_reasons_json'], $row['risks_json']);
        return $row;
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_provider_routing_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }
}
