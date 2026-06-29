<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class FulfilmentRequested implements DomainEvent
{
    public function __construct(
        private readonly string $fulfilmentId,
        private readonly string $repairOrderId,
        private readonly string $repairCaseId,
        private readonly string $providerId,
        private readonly string $requestedBy,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'repair.fulfilment_requested';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return ['fulfilment_id' => $this->fulfilmentId, 'repair_order_id' => $this->repairOrderId, 'repair_case_id' => $this->repairCaseId, 'provider_id' => $this->providerId, 'requested_by' => $this->requestedBy];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
