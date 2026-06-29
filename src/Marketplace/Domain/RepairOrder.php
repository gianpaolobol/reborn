<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

final class RepairOrder
{
    /** @param array<string, mixed> $order */
    public function __construct(
        public readonly string $id,
        public readonly string $quoteRequestId,
        public readonly string $providerMatchId,
        public readonly string $repairCaseId,
        public readonly string $providerId,
        public readonly string $orderedBy,
        public readonly string $status,
        public readonly string $currency,
        public readonly int $subtotalCents,
        public readonly int $platformFeeCents,
        public readonly int $providerPayoutCents,
        public readonly int $totalCents,
        public readonly array $order,
        public readonly string $createdAt,
        public readonly ?string $confirmedAt,
        public readonly ?string $cancelledAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $order = json_decode((string) ($row['order_json'] ?? '{}'), true);
        if (!is_array($order)) {
            $order = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['quote_request_id'],
            (string) $row['provider_match_id'],
            (string) $row['repair_case_id'],
            (string) $row['provider_id'],
            (string) $row['ordered_by'],
            (string) $row['status'],
            (string) $row['currency'],
            (int) $row['subtotal_cents'],
            (int) $row['platform_fee_cents'],
            (int) $row['provider_payout_cents'],
            (int) $row['total_cents'],
            $order,
            (string) $row['created_at'],
            $row['confirmed_at'] !== null ? (string) $row['confirmed_at'] : null,
            $row['cancelled_at'] !== null ? (string) $row['cancelled_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'quote_request_id' => $this->quoteRequestId,
            'provider_match_id' => $this->providerMatchId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'ordered_by' => $this->orderedBy,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal_cents' => $this->subtotalCents,
            'platform_fee_cents' => $this->platformFeeCents,
            'provider_payout_cents' => $this->providerPayoutCents,
            'total_cents' => $this->totalCents,
            'order_json' => $this->order,
            'created_at' => $this->createdAt,
            'confirmed_at' => $this->confirmedAt,
            'cancelled_at' => $this->cancelledAt,
        ];
    }
}
