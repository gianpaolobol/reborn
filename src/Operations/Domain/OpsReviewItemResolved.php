<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class OpsReviewItemResolved implements DomainEvent
{
    public function __construct(
        private readonly string $reviewItemId,
        private readonly string $resolution,
        private readonly string $actorId,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'ops.review_item_resolved';
    }

    public function payload(): array
    {
        return ['review_item_id' => $this->reviewItemId, 'resolution' => $this->resolution, 'actor_id' => $this->actorId];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
