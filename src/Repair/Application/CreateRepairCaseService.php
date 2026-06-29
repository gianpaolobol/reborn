<?php

declare(strict_types=1);

namespace Reborn\Repair\Application;

use Reborn\Repair\Domain\RepairCaseCreated;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Domain\EventBus;

final class CreateRepairCaseService
{
    public function __construct(private readonly RepairCaseRepository $repository, private readonly EventBus $eventBus)
    {
    }

    public function handle(array $data): array
    {
        $case = $this->repository->create($data);
        $this->eventBus->publish(new RepairCaseCreated($case->id, $case->category, gmdate('c')));

        return $case->toArray();
    }
}
