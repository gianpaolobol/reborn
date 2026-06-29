<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\RepairPathDecisionRepository;

final class GetRepairPathDecisionService
{
    public function __construct(private readonly RepairPathDecisionRepository $decisions)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->decisions->find($id)?->toArray();
    }
}
