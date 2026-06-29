<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

final class PaymentIntent
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $id,
        public readonly string $repairOrderId,
        public readonly string $quoteRequestId,
        public readonly string $repairCaseId,
        public readonly string $requestedBy,
        public readonly string $provider,
        public readonly string $status,
        public readonly string $currency,
        public readonly int $amountCents,
        public readonly string $clientSecret,
        public readonly ?string $paymentUrl,
        public readonly array $metadata,
        public readonly string $createdAt,
        public readonly string $expiresAt,
        public readonly ?string $confirmedAt,
        public readonly ?string $cancelledAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $metadata = json_decode((string) ($row['metadata_json'] ?? '{}'), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['repair_order_id'],
            (string) $row['quote_request_id'],
            (string) $row['repair_case_id'],
            (string) $row['requested_by'],
            (string) $row['provider'],
            (string) $row['status'],
            (string) $row['currency'],
            (int) $row['amount_cents'],
            (string) $row['client_secret'],
            $row['payment_url'] !== null ? (string) $row['payment_url'] : null,
            $metadata,
            (string) $row['created_at'],
            (string) $row['expires_at'],
            $row['confirmed_at'] !== null ? (string) $row['confirmed_at'] : null,
            $row['cancelled_at'] !== null ? (string) $row['cancelled_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'repair_order_id' => $this->repairOrderId,
            'quote_request_id' => $this->quoteRequestId,
            'repair_case_id' => $this->repairCaseId,
            'requested_by' => $this->requestedBy,
            'provider' => $this->provider,
            'status' => $this->status,
            'currency' => $this->currency,
            'amount_cents' => $this->amountCents,
            'client_secret' => $this->clientSecret,
            'payment_url' => $this->paymentUrl,
            'metadata_json' => $this->metadata,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'confirmed_at' => $this->confirmedAt,
            'cancelled_at' => $this->cancelledAt,
        ];
    }
}
