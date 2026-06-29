<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Application;

use Reborn\Fulfilment\Domain\RepairFulfilmentRepository;

final class ListRepairFulfilmentsService
{
    public function __construct(private readonly RepairFulfilmentRepository $fulfilments)
    {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairOrderId): array
    {
        return array_map(static fn($fulfilment): array => $fulfilment->toArray(), $this->fulfilments->listByRepairOrder($repairOrderId));
    }
}
