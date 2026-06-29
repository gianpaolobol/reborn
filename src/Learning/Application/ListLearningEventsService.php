<?php

declare(strict_types=1);

namespace Reborn\Learning\Application;

use Reborn\Learning\Domain\RepairLearningEventRepository;

final class ListLearningEventsService
{
    public function __construct(private readonly RepairLearningEventRepository $events)
    {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairCaseId): array
    {
        return array_map(static fn($event): array => $event->toArray(), $this->events->listByRepairCase($repairCaseId));
    }
}
