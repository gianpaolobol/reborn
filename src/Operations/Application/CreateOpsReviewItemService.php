<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Identity\Domain\User;
use Reborn\Operations\Domain\AdminOperationsRepository;
use Reborn\Operations\Domain\OpsReviewItemCreated;
use Reborn\Shared\Domain\EventBus;

final class CreateOpsReviewItemService
{
    public function __construct(
        private readonly AdminOperationsRepository $repository,
        private readonly EventBus $eventBus,
    ) {
    }

    public function handle(User $actor, array $payload): array
    {
        $item = $this->repository->createReviewItem($actor, $payload);
        $this->repository->audit($actor, 'ops_review_item_created', 'ops_review_item', $item->id, ['category' => $item->category, 'priority' => $item->priority]);
        $this->eventBus->publish(new OpsReviewItemCreated($item->id, $item->category, $item->priority, $actor->id, gmdate('c')));

        return ['review_item' => $item->toArray()];
    }
}
