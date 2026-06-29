<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Infrastructure;

use PDO;
use Reborn\Marketplace\Domain\RepairOrder;
use Reborn\Marketplace\Domain\RepairOrderRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteRepairOrderRepository implements RepairOrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createFromQuote(
        string $quoteRequestId,
        string $providerMatchId,
        string $repairCaseId,
        string $providerId,
        string $orderedBy,
        string $currency,
        int $subtotalCents,
        int $platformFeeCents,
        int $providerPayoutCents,
        int $totalCents,
        array $order
    ): RepairOrder {
        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO repair_orders (id, quote_request_id, provider_match_id, repair_case_id, provider_id, ordered_by, status, currency, subtotal_cents, platform_fee_cents, provider_payout_cents, total_cents, order_json, created_at, confirmed_at, cancelled_at) VALUES (:id, :quote_request_id, :provider_match_id, :repair_case_id, :provider_id, :ordered_by, :status, :currency, :subtotal_cents, :platform_fee_cents, :provider_payout_cents, :total_cents, :order_json, :created_at, :confirmed_at, :cancelled_at)');
        $stmt->execute([
            'id' => $id,
            'quote_request_id' => $quoteRequestId,
            'provider_match_id' => $providerMatchId,
            'repair_case_id' => $repairCaseId,
            'provider_id' => $providerId,
            'ordered_by' => $orderedBy,
            'status' => 'created',
            'currency' => $currency,
            'subtotal_cents' => $subtotalCents,
            'platform_fee_cents' => $platformFeeCents,
            'provider_payout_cents' => $providerPayoutCents,
            'total_cents' => $totalCents,
            'order_json' => json_encode($order, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'confirmed_at' => null,
            'cancelled_at' => null,
        ]);

        $orderModel = $this->find($id);
        if ($orderModel === null) {
            throw new \RuntimeException('Repair order creation failed.');
        }

        return $orderModel;
    }

    public function find(string $id): ?RepairOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_orders WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? RepairOrder::fromRow($row) : null;
    }

    public function listByRepairCase(string $repairCaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_orders WHERE repair_case_id = :repair_case_id ORDER BY created_at DESC');
        $stmt->execute(['repair_case_id' => $repairCaseId]);

        return array_map(static fn(array $row): RepairOrder => RepairOrder::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
