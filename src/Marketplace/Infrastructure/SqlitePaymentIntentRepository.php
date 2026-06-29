<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Infrastructure;

use PDO;
use Reborn\Marketplace\Domain\PaymentIntent;
use Reborn\Marketplace\Domain\PaymentIntentRepository;
use Reborn\Marketplace\Domain\RepairOrder;
use Reborn\Shared\Support\Uuid;

final class SqlitePaymentIntentRepository implements PaymentIntentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createMockIntent(RepairOrder $order, string $requestedBy, array $metadata): PaymentIntent
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $expiresAt = gmdate('c', time() + (30 * 60));
        $clientSecret = 'rbn_pi_' . bin2hex(random_bytes(18));
        $paymentUrl = '/prototype/index.html#/checkout?payment_intent=' . rawurlencode($id);

        $stmt = $this->pdo->prepare('INSERT INTO payment_intents (id, repair_order_id, quote_request_id, repair_case_id, requested_by, provider, status, currency, amount_cents, client_secret, payment_url, metadata_json, created_at, expires_at, confirmed_at, cancelled_at) VALUES (:id, :repair_order_id, :quote_request_id, :repair_case_id, :requested_by, :provider, :status, :currency, :amount_cents, :client_secret, :payment_url, :metadata_json, :created_at, :expires_at, :confirmed_at, :cancelled_at)');
        $stmt->execute([
            'id' => $id,
            'repair_order_id' => $order->id,
            'quote_request_id' => $order->quoteRequestId,
            'repair_case_id' => $order->repairCaseId,
            'requested_by' => $requestedBy,
            'provider' => 'mock',
            'status' => 'requires_mock_confirmation',
            'currency' => $order->currency,
            'amount_cents' => $order->totalCents,
            'client_secret' => $clientSecret,
            'payment_url' => $paymentUrl,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'expires_at' => $expiresAt,
            'confirmed_at' => null,
            'cancelled_at' => null,
        ]);

        $intent = $this->find($id);
        if ($intent === null) {
            throw new \RuntimeException('Payment intent creation failed.');
        }

        return $intent;
    }

    public function find(string $id): ?PaymentIntent
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_intents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? PaymentIntent::fromRow($row) : null;
    }

    public function listByRepairOrder(string $repairOrderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_intents WHERE repair_order_id = :repair_order_id ORDER BY created_at DESC');
        $stmt->execute(['repair_order_id' => $repairOrderId]);

        return array_map(static fn(array $row): PaymentIntent => PaymentIntent::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function confirmMock(string $id): PaymentIntent
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE payment_intents SET status = :status, confirmed_at = :confirmed_at WHERE id = :id AND status = :expected_status');
        $stmt->execute([
            'id' => $id,
            'status' => 'mock_authorized',
            'confirmed_at' => $now,
            'expected_status' => 'requires_mock_confirmation',
        ]);

        $intent = $this->find($id);
        if ($intent === null) {
            throw new \RuntimeException('Payment intent not found after mock confirmation.');
        }

        return $intent;
    }
}
