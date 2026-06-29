<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\RepairOrderRepository;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Http\NotFoundException;

final class ListRepairOrdersService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RepairOrderRepository $orders,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairCaseId): array
    {
        if ($this->repairCases->find($repairCaseId) === null) {
            throw new NotFoundException('Repair case not found.');
        }

        return array_map(static fn($order): array => $order->toArray(), $this->orders->listByRepairCase($repairCaseId));
    }
}
