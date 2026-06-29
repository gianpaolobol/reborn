<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Identity\Domain\User;
use Reborn\Operations\Domain\AdminOperationsRepository;
use Reborn\Operations\Domain\OpsReviewItemAssigned;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;

final class AssignOpsReviewItemService
{
    public function __construct(private readonly AdminOperationsRepository $repository, private readonly EventBus $eventBus)
    {
    }

    public function handle(string $id, User $actor, array $payload): array
    {
        if ($this->repository->findReviewItem($id) === null) {
            throw new NotFoundException('Operations review item not found.');
        }
        $assignedTo = isset($payload['assigned_to']) ? (string) $payload['assigned_to'] : $actor->id;
        $item = $this->repository->assignReviewItem($id, $actor, $assignedTo);
        $this->repository->audit($actor, 'ops_review_item_assigned', 'ops_review_item', $item->id, ['assigned_to' => $item->assignedTo]);
        $this->eventBus->publish(new OpsReviewItemAssigned($item->id, $item->assignedTo, $actor->id, gmdate('c')));

        return ['review_item' => $item->toArray()];
    }
}
