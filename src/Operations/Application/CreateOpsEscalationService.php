<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Identity\Domain\User;
use Reborn\Operations\Domain\AdminOperationsRepository;
use Reborn\Operations\Domain\OpsEscalationCreated;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;

final class CreateOpsEscalationService
{
    public function __construct(private readonly AdminOperationsRepository $repository, private readonly EventBus $eventBus)
    {
    }

    public function handle(string $reviewItemId, User $actor, array $payload): array
    {
        if ($this->repository->findReviewItem($reviewItemId) === null) {
            throw new NotFoundException('Operations review item not found.');
        }
        $escalation = $this->repository->createEscalation($reviewItemId, $actor, $payload);
        $this->repository->audit($actor, 'ops_escalation_created', 'ops_review_item', $reviewItemId, ['escalation_level' => $escalation->escalationLevel]);
        $this->eventBus->publish(new OpsEscalationCreated($escalation->id, $reviewItemId, $escalation->escalationLevel, $actor->id, gmdate('c')));

        return ['escalation' => $escalation->toArray()];
    }
}
