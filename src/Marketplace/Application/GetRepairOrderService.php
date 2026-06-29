<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\RepairOrderRepository;

final class GetRepairOrderService
{
    public function __construct(private readonly RepairOrderRepository $orders)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->orders->find($id)?->toArray();
    }
}
