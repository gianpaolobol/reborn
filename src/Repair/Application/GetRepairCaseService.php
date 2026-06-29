<?php

declare(strict_types=1);

namespace Reborn\Repair\Application;

use Reborn\Repair\Domain\RepairCase;
use Reborn\Repair\Domain\RepairCaseRepository;

final class GetRepairCaseService
{
    public function __construct(private readonly RepairCaseRepository $repository)
    {
    }

    public function find(string $id): ?RepairCase
    {
        return $this->repository->find($id);
    }

    public function handle(string $id): ?array
    {
        $case = $this->find($id);
        return $case?->toArray();
    }
}
