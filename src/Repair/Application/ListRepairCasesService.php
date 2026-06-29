<?php

declare(strict_types=1);

namespace Reborn\Repair\Application;

use Reborn\Repair\Domain\RepairCaseRepository;

final class ListRepairCasesService
{
    public function __construct(private readonly RepairCaseRepository $repository)
    {
    }

    public function handle(int $limit = 50): array
    {
        return array_map(static fn($case) => $case->toArray(), $this->repository->list($limit));
    }
}
