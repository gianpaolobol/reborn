<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class OpsModerationActionRecorded implements DomainEvent
{
    public function __construct(
        private readonly string $moderationActionId,
        private readonly string $reviewItemId,
        private readonly string $actionType,
        private readonly string $actorId,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'ops.moderation_action_recorded';
    }

    public function payload(): array
    {
        return ['moderation_action_id' => $this->moderationActionId, 'review_item_id' => $this->reviewItemId, 'action_type' => $this->actionType, 'actor_id' => $this->actorId];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
