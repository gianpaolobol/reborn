<?php

declare(strict_types=1);

namespace Reborn\Provider\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderQuoteEstimated implements DomainEvent
{
    public function __construct(
        private readonly string $quoteRequestId,
        private readonly string $repairCaseId,
        private readonly string $providerId,
        private readonly int $totalCents,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'quote.estimated';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'quote_request_id' => $this->quoteRequestId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'total_cents' => $this->totalCents,
            'currency' => 'EUR',
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
