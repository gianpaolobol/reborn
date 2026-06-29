<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class FulfilmentDispatchGovernanceService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'summary' => [
                'dispatch_policies' => $this->count('platform_dispatch_policies', "status = 'active'"),
                'dispatches' => $this->count('platform_fulfilment_dispatches'),
                'active_dispatches' => $this->count('platform_fulfilment_dispatches', "status IN ('planned','approved_for_dispatch','dispatched','in_transit','delivered','proof_pending','proof_review')"),
                'tracking_events' => $this->count('platform_shipment_tracking_events'),
                'proofs_pending_review' => $this->count('platform_proof_of_repair_records', "status = 'pending_review'"),
                'open_reviews' => $this->count('platform_dispatch_review_items', "status IN ('open','assigned')"),
            ],
            'latest_dispatches' => $this->dispatches('all', 5),
            'latest_tracking_events' => $this->shipmentTrackingEvents(null, 8),
            'pending_proofs' => $this->proofOfRepairRecords('pending_review', 5),
            'open_reviews' => $this->dispatchReviews('active', 5),
            'policies' => $this->dispatchPolicies('active'),
            'scope_note' => 'Step 34 governs pilot dispatch, local/mock shipment tracking and proof-of-repair. It does not book real couriers, labels, insurance or returns.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function dispatchPolicies(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_dispatch_policies';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY priority ASC, name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $row): array {
            $row['priority'] = (int) $row['priority'];
            $row['rules'] = $this->decodeJson($row['rules_json'] ?? '{}');
            unset($row['rules_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function dispatches(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = $this->dispatchSelectSql();
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE d.status IN ('planned','approved_for_dispatch','dispatched','in_transit','delivered','proof_pending','proof_review')";
            } else {
                $sql .= ' WHERE d.status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY d.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeDispatch'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createDispatch(array $body, ?string $userId): array
    {
        $routingMatchId = trim((string) ($body['routing_match_id'] ?? '')) ?: null;
        if ($routingMatchId === null) {
            $routingMatchId = $this->latestRoutingMatchId();
        }
        if ($routingMatchId === null) {
            throw new ValidationException(['routing_match_id' => ['A routing match is required before creating a dispatch. Evaluate a routing request first.']]);
        }

        $match = $this->requireRoutingMatch($routingMatchId);
        $id = Uuid::v4();
        $now = gmdate('c');
        $dispatchCode = 'DISPATCH-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $fulfilmentMode = trim((string) ($body['fulfilment_mode'] ?? 'shipped')) ?: 'shipped';
        $allowedModes = ['shipped', 'local_pickup', 'provider_dropoff', 'digital_handoff'];
        if (!in_array($fulfilmentMode, $allowedModes, true)) {
            throw new ValidationException(['fulfilment_mode' => ['Unsupported fulfilment mode.']]);
        }
        $carrier = trim((string) ($body['carrier'] ?? ($fulfilmentMode === 'shipped' ? 'mock_carrier' : 'local_operator'))) ?: null;
        $trackingNumber = trim((string) ($body['tracking_number'] ?? ('RB-' . strtoupper(substr(str_replace('-', '', Uuid::v4()), 0, 8))))) ?: null;
        $estimatedDispatchAt = trim((string) ($body['estimated_dispatch_at'] ?? gmdate('c', time() + 86400))) ?: gmdate('c', time() + 86400);
        $leadDays = max(1, (int) ($match['estimated_lead_time_days'] ?? 3));
        $estimatedDeliveryAt = trim((string) ($body['estimated_delivery_at'] ?? gmdate('c', time() + (($leadDays + 1) * 86400)))) ?: gmdate('c', time() + (($leadDays + 1) * 86400));
        $score = (int) ($match['match_score'] ?? 0);
        $dispatchDecision = $score >= 70 ? 'ready_for_dispatch' : 'manual_review_required';
        $status = $dispatchDecision === 'ready_for_dispatch' ? 'planned' : 'needs_review';

        $stmt = $this->pdo->prepare('INSERT INTO platform_fulfilment_dispatches (id, dispatch_code, routing_request_id, routing_match_id, provider_capability_id, machine_profile_id, repair_order_id, fulfilment_id, status, dispatch_decision, fulfilment_mode, carrier, tracking_number, destination_country, estimated_dispatch_at, estimated_delivery_at, package_requirements_json, operator_notes, created_by, created_at, updated_at, dispatched_at, delivered_at, closed_at) VALUES (:id, :dispatch_code, :routing_request_id, :routing_match_id, :provider_capability_id, :machine_profile_id, :repair_order_id, :fulfilment_id, :status, :dispatch_decision, :fulfilment_mode, :carrier, :tracking_number, :destination_country, :estimated_dispatch_at, :estimated_delivery_at, :package_requirements_json, :operator_notes, :created_by, :created_at, :updated_at, :dispatched_at, :delivered_at, :closed_at)');
        $stmt->execute([
            'id' => $id,
            'dispatch_code' => $dispatchCode,
            'routing_request_id' => $match['routing_request_id'] ?? null,
            'routing_match_id' => $routingMatchId,
            'provider_capability_id' => $match['provider_capability_id'] ?? null,
            'machine_profile_id' => $match['machine_profile_id'] ?? null,
            'repair_order_id' => trim((string) ($body['repair_order_id'] ?? '')) ?: null,
            'fulfilment_id' => trim((string) ($body['fulfilment_id'] ?? '')) ?: null,
            'status' => $status,
            'dispatch_decision' => $dispatchDecision,
            'fulfilment_mode' => $fulfilmentMode,
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'destination_country' => strtoupper(trim((string) ($body['destination_country'] ?? ($match['destination_country'] ?? 'IT')))) ?: 'IT',
            'estimated_dispatch_at' => $estimatedDispatchAt,
            'estimated_delivery_at' => $estimatedDeliveryAt,
            'package_requirements_json' => json_encode($body['package_requirements'] ?? ['protective_packaging' => true, 'label_required' => $fulfilmentMode === 'shipped'], JSON_THROW_ON_ERROR),
            'operator_notes' => trim((string) ($body['operator_notes'] ?? 'Pilot dispatch created from provider routing match.')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'dispatched_at' => null,
            'delivered_at' => null,
            'closed_at' => null,
        ]);

        $this->recordShipmentEvent($id, [
            'event_type' => 'dispatch_created',
            'status' => 'planned',
            'location' => $match['provider_name'] ?? 'provider',
            'message' => sprintf('Dispatch %s created from routing match.', $dispatchCode),
            'evidence' => ['routing_match_id' => $routingMatchId, 'match_score' => $score],
        ], $userId);

        $review = null;
        if ($dispatchDecision !== 'ready_for_dispatch') {
            $review = $this->createReview($id, 'high', 'Dispatch created from low-confidence routing match and requires operator approval.', $userId);
        }

        $this->audit('dispatch_created', 'dispatch', $id, sprintf('Dispatch %s created.', $dispatchCode), ['routing_match_id' => $routingMatchId, 'decision' => $dispatchDecision], $userId);

        return ['dispatch' => $this->requireDispatch($id), 'review_item' => $review];
    }

    /** @return array<string, mixed> */
    public function advanceDispatch(string $id, array $body, ?string $userId): array
    {
        $dispatch = $this->requireDispatch($id);
        $action = trim((string) ($body['action'] ?? 'confirm_dispatch')) ?: 'confirm_dispatch';
        $allowed = ['approve_dispatch', 'confirm_dispatch', 'mark_in_transit', 'mark_delivered', 'mark_proof_pending', 'close'];
        if (!in_array($action, $allowed, true)) {
            throw new ValidationException(['action' => ['Unsupported dispatch action.']]);
        }

        $statusMap = [
            'approve_dispatch' => 'approved_for_dispatch',
            'confirm_dispatch' => 'dispatched',
            'mark_in_transit' => 'in_transit',
            'mark_delivered' => 'delivered',
            'mark_proof_pending' => 'proof_pending',
            'close' => 'completed',
        ];
        $newStatus = $statusMap[$action];
        $decision = $action === 'approve_dispatch' ? 'operator_approved_dispatch' : ($dispatch['dispatch_decision'] ?: 'ready_for_dispatch');
        $now = gmdate('c');
        $fields = ['status = :status', 'dispatch_decision = :dispatch_decision', 'updated_at = :updated_at'];
        $params = ['status' => $newStatus, 'dispatch_decision' => $decision, 'updated_at' => $now, 'id' => $id];
        if ($action === 'confirm_dispatch') { $fields[] = 'dispatched_at = :dispatched_at'; $params['dispatched_at'] = $now; }
        if ($action === 'mark_delivered') { $fields[] = 'delivered_at = :delivered_at'; $params['delivered_at'] = $now; }
        if ($action === 'close') { $fields[] = 'closed_at = :closed_at'; $params['closed_at'] = $now; }
        $stmt = $this->pdo->prepare('UPDATE platform_fulfilment_dispatches SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);

        $eventType = match ($action) {
            'approve_dispatch' => 'operator_dispatch_approved',
            'confirm_dispatch' => 'parcel_dispatched',
            'mark_in_transit' => 'parcel_in_transit',
            'mark_delivered' => 'parcel_delivered',
            'mark_proof_pending' => 'proof_requested',
            default => 'dispatch_closed',
        };
        $this->recordShipmentEvent($id, [
            'event_type' => $eventType,
            'status' => $newStatus,
            'location' => trim((string) ($body['location'] ?? 'pilot_operations')) ?: 'pilot_operations',
            'message' => trim((string) ($body['message'] ?? sprintf('Dispatch action %s recorded.', $action))) ?: sprintf('Dispatch action %s recorded.', $action),
            'evidence' => $body['evidence'] ?? ['source' => 'operator_action'],
        ], $userId);
        $this->audit('dispatch_advanced', 'dispatch', $id, sprintf('Dispatch advanced with action %s.', $action), ['status' => $newStatus], $userId);
        return $this->requireDispatch($id);
    }

    /** @return list<array<string, mixed>> */
    public function shipmentTrackingEvents(?string $dispatchId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT e.*, d.dispatch_code FROM platform_shipment_tracking_events e JOIN platform_fulfilment_dispatches d ON d.id = e.dispatch_id';
        $params = [];
        if ($dispatchId !== null && $dispatchId !== '') {
            $sql .= ' WHERE e.dispatch_id = :dispatch_id';
            $params['dispatch_id'] = $dispatchId;
        }
        $sql .= ' ORDER BY e.occurred_at DESC, e.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '{}');
            unset($row['evidence_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function recordShipmentEvent(string $dispatchId, array $body, ?string $userId): array
    {
        $this->requireDispatch($dispatchId);
        $id = Uuid::v4();
        $now = gmdate('c');
        $eventType = trim((string) ($body['event_type'] ?? 'manual_update')) ?: 'manual_update';
        $status = trim((string) ($body['status'] ?? 'recorded')) ?: 'recorded';
        $stmt = $this->pdo->prepare('INSERT INTO platform_shipment_tracking_events (id, dispatch_id, status, event_type, location, message, evidence_json, created_by, occurred_at, created_at) VALUES (:id, :dispatch_id, :status, :event_type, :location, :message, :evidence_json, :created_by, :occurred_at, :created_at)');
        $stmt->execute([
            'id' => $id,
            'dispatch_id' => $dispatchId,
            'status' => $status,
            'event_type' => $eventType,
            'location' => trim((string) ($body['location'] ?? 'pilot_operations')) ?: null,
            'message' => trim((string) ($body['message'] ?? 'Tracking event recorded.')) ?: 'Tracking event recorded.',
            'evidence_json' => json_encode($body['evidence'] ?? [], JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'occurred_at' => trim((string) ($body['occurred_at'] ?? $now)) ?: $now,
            'created_at' => $now,
        ]);

        if (in_array($status, ['dispatched', 'in_transit', 'delivered', 'proof_pending'], true)) {
            $fields = ['status = :status', 'updated_at = :updated_at'];
            $params = ['status' => $status, 'updated_at' => $now, 'id' => $dispatchId];
            if ($status === 'dispatched') { $fields[] = 'dispatched_at = COALESCE(dispatched_at, :dispatched_at)'; $params['dispatched_at'] = $now; }
            if ($status === 'delivered') { $fields[] = 'delivered_at = COALESCE(delivered_at, :delivered_at)'; $params['delivered_at'] = $now; }
            $stmt = $this->pdo->prepare('UPDATE platform_fulfilment_dispatches SET ' . implode(', ', $fields) . ' WHERE id = :id');
            $stmt->execute($params);
        }

        $this->audit('shipment_event_recorded', 'shipment_event', $id, sprintf('Shipment event %s recorded.', $eventType), ['dispatch_id' => $dispatchId, 'status' => $status], $userId);
        return $this->requireShipmentEvent($id);
    }

    /** @return list<array<string, mixed>> */
    public function proofOfRepairRecords(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT p.*, d.dispatch_code, d.status AS dispatch_status, c.provider_name FROM platform_proof_of_repair_records p JOIN platform_fulfilment_dispatches d ON d.id = p.dispatch_id LEFT JOIN platform_provider_capability_profiles c ON c.id = d.provider_capability_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE p.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY p.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeProof'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createProofOfRepair(string $dispatchId, array $body, ?string $userId): array
    {
        $this->requireDispatch($dispatchId);
        $id = Uuid::v4();
        $now = gmdate('c');
        $qualityScore = max(0, min(100, (int) ($body['quality_score'] ?? 75)));
        $stmt = $this->pdo->prepare('INSERT INTO platform_proof_of_repair_records (id, dispatch_id, status, proof_type, summary, evidence_json, quality_score, customer_acceptance_status, customer_notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :dispatch_id, :status, :proof_type, :summary, :evidence_json, :quality_score, :customer_acceptance_status, :customer_notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'dispatch_id' => $dispatchId,
            'status' => 'pending_review',
            'proof_type' => trim((string) ($body['proof_type'] ?? 'photo_and_notes')) ?: 'photo_and_notes',
            'summary' => trim((string) ($body['summary'] ?? 'Pilot proof-of-repair evidence submitted.')) ?: 'Pilot proof-of-repair evidence submitted.',
            'evidence_json' => json_encode($body['evidence'] ?? ['photo_stub' => 'local://proof/mock-image.jpg', 'functional_test' => true], JSON_THROW_ON_ERROR),
            'quality_score' => $qualityScore,
            'customer_acceptance_status' => trim((string) ($body['customer_acceptance_status'] ?? 'not_requested')) ?: 'not_requested',
            'customer_notes' => trim((string) ($body['customer_notes'] ?? '')) ?: null,
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        $this->pdo->prepare("UPDATE platform_fulfilment_dispatches SET status = 'proof_review', updated_at = :updated_at WHERE id = :id")->execute(['updated_at' => $now, 'id' => $dispatchId]);
        if ($qualityScore < 70) {
            $this->createReview($dispatchId, 'high', 'Proof-of-repair quality score is below pilot acceptance threshold.', $userId);
        }
        $this->audit('proof_of_repair_submitted', 'proof_of_repair', $id, 'Proof-of-repair submitted for operator review.', ['dispatch_id' => $dispatchId, 'quality_score' => $qualityScore], $userId);
        return $this->requireProof($id);
    }

    /** @return array<string, mixed> */
    public function reviewProofOfRepair(string $id, array $body, ?string $userId): array
    {
        $proof = $this->requireProof($id);
        $decision = trim((string) ($body['decision'] ?? 'accepted')) ?: 'accepted';
        $allowed = ['accepted', 'accepted_with_notes', 'rework_required', 'rejected'];
        if (!in_array($decision, $allowed, true)) {
            throw new ValidationException(['decision' => ['Unsupported proof-of-repair decision.']]);
        }
        $proofStatus = in_array($decision, ['accepted', 'accepted_with_notes'], true) ? 'accepted' : ($decision === 'rework_required' ? 'rework_required' : 'rejected');
        $customerStatus = $proofStatus === 'accepted' ? 'ready_for_customer_acceptance' : 'not_requested';
        $dispatchStatus = $proofStatus === 'accepted' ? 'proof_accepted' : 'rework_required';
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_proof_of_repair_records SET status = :status, customer_acceptance_status = :customer_acceptance_status, customer_notes = :customer_notes, reviewed_by = :reviewed_by, updated_at = :updated_at, reviewed_at = :reviewed_at WHERE id = :id');
        $stmt->execute([
            'status' => $proofStatus,
            'customer_acceptance_status' => $customerStatus,
            'customer_notes' => trim((string) ($body['notes'] ?? 'Proof reviewed.')) ?: null,
            'reviewed_by' => $userId,
            'updated_at' => $now,
            'reviewed_at' => $now,
            'id' => $id,
        ]);
        $this->pdo->prepare('UPDATE platform_fulfilment_dispatches SET status = :status, updated_at = :updated_at WHERE id = :id')->execute(['status' => $dispatchStatus, 'updated_at' => $now, 'id' => $proof['dispatch_id']]);
        $this->audit('proof_of_repair_reviewed', 'proof_of_repair', $id, sprintf('Proof-of-repair reviewed with decision %s.', $decision), ['dispatch_status' => $dispatchStatus], $userId);
        return $this->requireProof($id);
    }

    /** @return list<array<string, mixed>> */
    public function dispatchReviews(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT rv.*, d.dispatch_code, d.status AS dispatch_status, d.dispatch_decision FROM platform_dispatch_review_items rv JOIN platform_fulfilment_dispatches d ON d.id = rv.dispatch_id';
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
    public function reviewDispatch(string $id, array $body, ?string $userId): array
    {
        $review = $this->requireReview($id);
        $decision = trim((string) ($body['decision'] ?? 'approved_for_dispatch')) ?: 'approved_for_dispatch';
        $allowed = ['approved_for_dispatch', 'hold_for_operator', 'rework_required', 'blocked'];
        if (!in_array($decision, $allowed, true)) {
            throw new ValidationException(['decision' => ['Unsupported dispatch review decision.']]);
        }
        $dispatchStatus = $decision === 'approved_for_dispatch' ? 'approved_for_dispatch' : ($decision === 'blocked' ? 'blocked' : 'needs_review');
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_dispatch_review_items SET status = :status, decision = :decision, notes = :notes, reviewed_by = :reviewed_by, updated_at = :updated_at, reviewed_at = :reviewed_at WHERE id = :id');
        $stmt->execute(['status' => 'closed', 'decision' => $decision, 'notes' => trim((string) ($body['notes'] ?? 'Dispatch review completed.')), 'reviewed_by' => $userId, 'updated_at' => $now, 'reviewed_at' => $now, 'id' => $id]);
        $this->pdo->prepare('UPDATE platform_fulfilment_dispatches SET status = :status, dispatch_decision = :decision, updated_at = :updated_at WHERE id = :id')->execute(['status' => $dispatchStatus, 'decision' => $decision, 'updated_at' => $now, 'id' => $review['dispatch_id']]);
        $this->audit('dispatch_review_completed', 'dispatch_review', $id, sprintf('Dispatch review completed with decision %s.', $decision), ['dispatch_status' => $dispatchStatus], $userId);
        return $this->requireReview($id);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_dispatch_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function requireDispatch(string $id): array
    {
        $stmt = $this->pdo->prepare($this->dispatchSelectSql() . ' WHERE d.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['dispatch_id' => ['Dispatch not found.']]);
        }
        return $this->normalizeDispatch($row);
    }

    /** @return array<string, mixed> */
    private function requireRoutingMatch(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT m.*, rr.request_code, rr.destination_country, rr.priority, c.provider_id, c.provider_name, mp.machine_code, mp.machine_name FROM platform_provider_routing_matches m JOIN platform_fulfilment_routing_requests rr ON rr.id = m.routing_request_id JOIN platform_provider_capability_profiles c ON c.id = m.provider_capability_id LEFT JOIN platform_machine_profiles mp ON mp.id = m.machine_profile_id WHERE m.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['routing_match_id' => ['Routing match not found.']]);
        }
        $row['match_score'] = (int) $row['match_score'];
        $row['estimated_lead_time_days'] = (int) $row['estimated_lead_time_days'];
        return $row;
    }

    private function latestRoutingMatchId(): ?string
    {
        $stmt = $this->pdo->query("SELECT id FROM platform_provider_routing_matches WHERE status IN ('recommended','candidate') ORDER BY CASE status WHEN 'recommended' THEN 0 ELSE 1 END, match_score DESC, created_at DESC LIMIT 1");
        $id = $stmt ? $stmt->fetchColumn() : false;
        return $id ? (string) $id : null;
    }

    /** @return array<string, mixed> */
    private function requireShipmentEvent(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT e.*, d.dispatch_code FROM platform_shipment_tracking_events e JOIN platform_fulfilment_dispatches d ON d.id = e.dispatch_id WHERE e.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['shipment_event_id' => ['Shipment event not found.']]);
        }
        $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '{}');
        unset($row['evidence_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireProof(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, d.dispatch_code, d.status AS dispatch_status, c.provider_name FROM platform_proof_of_repair_records p JOIN platform_fulfilment_dispatches d ON d.id = p.dispatch_id LEFT JOIN platform_provider_capability_profiles c ON c.id = d.provider_capability_id WHERE p.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['proof_of_repair_id' => ['Proof-of-repair record not found.']]);
        }
        return $this->normalizeProof($row);
    }

    /** @return array<string, mixed> */
    private function createReview(string $dispatchId, string $priority, string $reason, ?string $userId): array
    {
        $existing = $this->pdo->prepare("SELECT id FROM platform_dispatch_review_items WHERE dispatch_id = :dispatch_id AND status IN ('open','assigned') ORDER BY created_at DESC LIMIT 1");
        $existing->execute(['dispatch_id' => $dispatchId]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            return $this->requireReview((string) $existingId);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_dispatch_review_items (id, dispatch_id, status, priority, review_reason, decision, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :dispatch_id, :status, :priority, :review_reason, :decision, :notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'dispatch_id' => $dispatchId,
            'status' => 'open',
            'priority' => $priority,
            'review_reason' => $reason,
            'decision' => null,
            'notes' => 'Review generated by Step 34 dispatch governance.',
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
        $stmt = $this->pdo->prepare('SELECT rv.*, d.dispatch_code, d.status AS dispatch_status, d.dispatch_decision FROM platform_dispatch_review_items rv JOIN platform_fulfilment_dispatches d ON d.id = rv.dispatch_id WHERE rv.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['dispatch_review_id' => ['Dispatch review item not found.']]);
        }
        return $row;
    }

    private function dispatchSelectSql(): string
    {
        return 'SELECT d.*, rr.request_code, m.rank AS routing_rank, m.match_score, c.provider_id, c.provider_name, mp.machine_code, mp.machine_name FROM platform_fulfilment_dispatches d LEFT JOIN platform_fulfilment_routing_requests rr ON rr.id = d.routing_request_id LEFT JOIN platform_provider_routing_matches m ON m.id = d.routing_match_id LEFT JOIN platform_provider_capability_profiles c ON c.id = d.provider_capability_id LEFT JOIN platform_machine_profiles mp ON mp.id = d.machine_profile_id';
    }

    /** @return array<string, mixed> */
    private function normalizeDispatch(array $row): array
    {
        $row['package_requirements'] = $this->decodeJson($row['package_requirements_json'] ?? '{}');
        if (isset($row['routing_rank'])) { $row['routing_rank'] = $row['routing_rank'] === null ? null : (int) $row['routing_rank']; }
        if (isset($row['match_score'])) { $row['match_score'] = $row['match_score'] === null ? null : (int) $row['match_score']; }
        unset($row['package_requirements_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeProof(array $row): array
    {
        $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '{}');
        $row['quality_score'] = (int) $row['quality_score'];
        unset($row['evidence_json']);
        return $row;
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

    private function count(string $table, ?string $where = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_dispatch_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
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
