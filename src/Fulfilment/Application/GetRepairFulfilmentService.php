<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Application;

use Reborn\Fulfilment\Domain\RepairFulfilmentRepository;

final class GetRepairFulfilmentService
{
    public function __construct(private readonly RepairFulfilmentRepository $fulfilments)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $fulfilment = $this->fulfilments->find($id);

        return $fulfilment?->toArray();
    }
}
