<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Identity\Domain\User;
use Reborn\Operations\Domain\AdminOperationsRepository;
use Reborn\Operations\Domain\OpsReviewItemResolved;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;

final class ResolveOpsReviewItemService
{
    public function __construct(private readonly AdminOperationsRepository $repository, private readonly EventBus $eventBus)
    {
    }

    public function handle(string $id, User $actor, array $payload): array
    {
        if ($this->repository->findReviewItem($id) === null) {
            throw new NotFoundException('Operations review item not found.');
        }
        $item = $this->repository->resolveReviewItem($id, $actor, $payload);
        $resolution = (string) ($payload['resolution'] ?? 'resolved');
        $this->repository->audit($actor, 'ops_review_item_resolved', 'ops_review_item', $item->id, ['resolution' => $resolution]);
        $this->eventBus->publish(new OpsReviewItemResolved($item->id, $resolution, $actor->id, gmdate('c')));

        return ['review_item' => $item->toArray()];
    }
}
