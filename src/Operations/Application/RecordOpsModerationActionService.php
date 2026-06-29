<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Identity\Domain\User;
use Reborn\Operations\Domain\AdminOperationsRepository;
use Reborn\Operations\Domain\OpsModerationActionRecorded;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;

final class RecordOpsModerationActionService
{
    public function __construct(private readonly AdminOperationsRepository $repository, private readonly EventBus $eventBus)
    {
    }

    public function handle(string $reviewItemId, User $actor, array $payload): array
    {
        if ($this->repository->findReviewItem($reviewItemId) === null) {
            throw new NotFoundException('Operations review item not found.');
        }
        $action = $this->repository->recordModerationAction($reviewItemId, $actor, $payload);
        $this->repository->audit($actor, 'ops_moderation_action_recorded', 'ops_review_item', $reviewItemId, ['action_type' => $action->actionType, 'target_type' => $action->targetType, 'target_id' => $action->targetId]);
        $this->eventBus->publish(new OpsModerationActionRecorded($action->id, $reviewItemId, $action->actionType, $actor->id, gmdate('c')));

        return ['moderation_action' => $action->toArray()];
    }
}
