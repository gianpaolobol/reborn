<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class OpsReviewItemAssigned implements DomainEvent
{
    public function __construct(
        private readonly string $reviewItemId,
        private readonly ?string $assignedTo,
        private readonly string $actorId,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'ops.review_item_assigned';
    }

    public function payload(): array
    {
        return ['review_item_id' => $this->reviewItemId, 'assigned_to' => $this->assignedTo, 'actor_id' => $this->actorId];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
