<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class OpsEscalationCreated implements DomainEvent
{
    public function __construct(
        private readonly string $escalationId,
        private readonly string $reviewItemId,
        private readonly string $escalationLevel,
        private readonly string $actorId,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'ops.escalation_created';
    }

    public function payload(): array
    {
        return ['escalation_id' => $this->escalationId, 'review_item_id' => $this->reviewItemId, 'escalation_level' => $this->escalationLevel, 'actor_id' => $this->actorId];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
