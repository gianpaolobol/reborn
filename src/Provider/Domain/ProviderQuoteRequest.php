<?php

declare(strict_types=1);

namespace Reborn\Provider\Domain;

final class ProviderQuoteRequest
{
    /** @param array<string, mixed> $quote */
    public function __construct(
        public readonly string $id,
        public readonly string $providerMatchId,
        public readonly string $repairCaseId,
        public readonly string $providerId,
        public readonly string $requestedBy,
        public readonly string $status,
        public readonly array $quote,
        public readonly string $createdAt,
        public readonly string $expiresAt,
        public readonly ?string $acceptedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $quote = json_decode((string) ($row['quote_json'] ?? '{}'), true);
        if (!is_array($quote)) {
            $quote = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['provider_match_id'],
            (string) $row['repair_case_id'],
            (string) $row['provider_id'],
            (string) $row['requested_by'],
            (string) $row['status'],
            $quote,
            (string) $row['created_at'],
            (string) $row['expires_at'],
            $row['accepted_at'] !== null ? (string) $row['accepted_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider_match_id' => $this->providerMatchId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'requested_by' => $this->requestedBy,
            'status' => $this->status,
            'quote_json' => $this->quote,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'accepted_at' => $this->acceptedAt,
        ];
    }
}
