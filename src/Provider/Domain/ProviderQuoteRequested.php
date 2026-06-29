<?php

declare(strict_types=1);

namespace Reborn\Provider\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderQuoteRequested implements DomainEvent
{
    public function __construct(
        private readonly string $providerMatchId,
        private readonly string $repairCaseId,
        private readonly string $providerId,
        private readonly string $requestedBy,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'quote.requested';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'provider_match_id' => $this->providerMatchId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'requested_by' => $this->requestedBy,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
