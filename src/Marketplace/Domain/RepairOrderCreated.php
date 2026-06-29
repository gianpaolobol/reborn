<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class RepairOrderCreated implements DomainEvent
{
    public function __construct(
        private readonly string $repairOrderId,
        private readonly string $quoteRequestId,
        private readonly string $repairCaseId,
        private readonly string $providerId,
        private readonly string $orderedBy,
        private readonly int $totalCents,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'repair.order_created';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'repair_order_id' => $this->repairOrderId,
            'quote_request_id' => $this->quoteRequestId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'ordered_by' => $this->orderedBy,
            'total_cents' => $this->totalCents,
            'currency' => 'EUR',
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
