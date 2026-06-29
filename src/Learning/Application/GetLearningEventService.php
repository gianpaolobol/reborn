<?php

declare(strict_types=1);

namespace Reborn\Learning\Application;

use Reborn\Learning\Domain\RepairLearningEventRepository;

final class GetLearningEventService
{
    public function __construct(private readonly RepairLearningEventRepository $events)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->events->find($id)?->toArray();
    }
}
