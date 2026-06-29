<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Infrastructure;

use PDO;
use Reborn\Fulfilment\Domain\RepairFulfilment;
use Reborn\Fulfilment\Domain\RepairFulfilmentRepository;
use Reborn\Marketplace\Domain\RepairOrder;
use Reborn\Shared\Support\Uuid;

final class SqliteRepairFulfilmentRepository implements RepairFulfilmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createFromRepairOrder(RepairOrder $order, string $requestedBy): RepairFulfilment
    {
        $existing = $this->listByRepairOrder($order->id);
        if ($existing !== []) {
            return $existing[0];
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $timeline = [[
            'event' => 'fulfilment_requested',
            'status' => 'awaiting_provider_acceptance',
            'actor_id' => $requestedBy,
            'note' => 'Repair order is ready for provider acceptance after mock payment authorization.',
            'occurred_at' => $now,
        ]];

        $stmt = $this->pdo->prepare('INSERT INTO repair_fulfilments (id, repair_order_id, quote_request_id, repair_case_id, provider_id, requested_by, accepted_by, status, provider_notes, tracking_reference, timeline_json, created_at, accepted_at, started_at, quality_checked_at, ready_at, completed_at, rejected_at, updated_at) VALUES (:id, :repair_order_id, :quote_request_id, :repair_case_id, :provider_id, :requested_by, :accepted_by, :status, :provider_notes, :tracking_reference, :timeline_json, :created_at, :accepted_at, :started_at, :quality_checked_at, :ready_at, :completed_at, :rejected_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'repair_order_id' => $order->id,
            'quote_request_id' => $order->quoteRequestId,
            'repair_case_id' => $order->repairCaseId,
            'provider_id' => $order->providerId,
            'requested_by' => $requestedBy,
            'accepted_by' => null,
            'status' => 'awaiting_provider_acceptance',
            'provider_notes' => null,
            'tracking_reference' => null,
            'timeline_json' => json_encode($timeline, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'accepted_at' => null,
            'started_at' => null,
            'quality_checked_at' => null,
            'ready_at' => null,
            'completed_at' => null,
            'rejected_at' => null,
            'updated_at' => $now,
        ]);

        $fulfilment = $this->find($id);
        if ($fulfilment === null) {
            throw new \RuntimeException('Repair fulfilment creation failed.');
        }

        $this->pdo->prepare('UPDATE repair_orders SET status = :status WHERE id = :id')->execute([
            'id' => $order->id,
            'status' => 'awaiting_provider_acceptance',
        ]);

        return $fulfilment;
    }

    public function find(string $id): ?RepairFulfilment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_fulfilments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? RepairFulfilment::fromRow($row) : null;
    }

    public function listByRepairOrder(string $repairOrderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_fulfilments WHERE repair_order_id = :repair_order_id ORDER BY created_at DESC');
        $stmt->execute(['repair_order_id' => $repairOrderId]);

        return array_map(static fn(array $row): RepairFulfilment => RepairFulfilment::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function acceptProvider(string $id, string $acceptedBy, ?string $providerNotes): RepairFulfilment
    {
        $current = $this->find($id);
        if ($current === null) {
            throw new \RuntimeException('Repair fulfilment not found.');
        }

        $now = gmdate('c');
        $timeline = $current->timeline;
        $timeline[] = [
            'event' => 'provider_accepted',
            'status' => 'accepted',
            'actor_id' => $acceptedBy,
            'note' => $providerNotes ?: 'Provider accepted the repair fulfilment workflow.',
            'occurred_at' => $now,
        ];

        $this->pdo->prepare('UPDATE repair_fulfilments SET accepted_by = :accepted_by, status = :status, provider_notes = :provider_notes, timeline_json = :timeline_json, accepted_at = :accepted_at, updated_at = :updated_at WHERE id = :id')->execute([
            'id' => $id,
            'accepted_by' => $acceptedBy,
            'status' => 'accepted',
            'provider_notes' => $providerNotes,
            'timeline_json' => json_encode($timeline, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'accepted_at' => $now,
            'updated_at' => $now,
        ]);

        $this->pdo->prepare('UPDATE repair_orders SET status = :status WHERE id = :id')->execute([
            'id' => $current->repairOrderId,
            'status' => 'provider_accepted',
        ]);

        return $this->find($id) ?? throw new \RuntimeException('Repair fulfilment not found after provider acceptance.');
    }

    public function updateStatus(string $id, string $status, ?string $note, string $actorId): RepairFulfilment
    {
        $current = $this->find($id);
        if ($current === null) {
            throw new \RuntimeException('Repair fulfilment not found.');
        }

        $now = gmdate('c');
        $timeline = $current->timeline;
        $timeline[] = [
            'event' => 'status_updated',
            'status' => $status,
            'actor_id' => $actorId,
            'note' => $note ?: 'Fulfilment status updated.',
            'occurred_at' => $now,
        ];

        $columns = [
            'in_progress' => 'started_at',
            'quality_check' => 'quality_checked_at',
            'ready_to_ship' => 'ready_at',
            'completed' => 'completed_at',
            'rejected' => 'rejected_at',
        ];
        $dateSql = isset($columns[$status]) ? ', ' . $columns[$status] . ' = :status_at' : '';

        $stmt = $this->pdo->prepare('UPDATE repair_fulfilments SET status = :status, provider_notes = COALESCE(:provider_notes, provider_notes), timeline_json = :timeline_json, updated_at = :updated_at' . $dateSql . ' WHERE id = :id');
        $params = [
            'id' => $id,
            'status' => $status,
            'provider_notes' => $note,
            'timeline_json' => json_encode($timeline, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ];
        if (isset($columns[$status])) {
            $params['status_at'] = $now;
        }
        $stmt->execute($params);

        $orderStatus = $status === 'completed' ? 'completed' : 'in_fulfilment';
        $this->pdo->prepare('UPDATE repair_orders SET status = :status, confirmed_at = CASE WHEN :status_done = 1 THEN COALESCE(confirmed_at, :now) ELSE confirmed_at END WHERE id = :id')->execute([
            'id' => $current->repairOrderId,
            'status' => $orderStatus,
            'status_done' => $status === 'completed' ? 1 : 0,
            'now' => $now,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('Repair fulfilment not found after status update.');
    }
}
