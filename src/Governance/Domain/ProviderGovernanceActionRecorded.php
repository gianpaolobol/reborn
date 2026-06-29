<?php

declare(strict_types=1);

namespace Reborn\Governance\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderGovernanceActionRecorded implements DomainEvent
{
    public function __construct(
        private readonly string $actionId,
        private readonly string $providerId,
        private readonly string $actionType,
        private readonly string $severity,
        private readonly float $scoreAdjustment,
        private readonly string $actorId,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'governance.provider_action_recorded';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'action_id' => $this->actionId,
            'provider_id' => $this->providerId,
            'action_type' => $this->actionType,
            'severity' => $this->severity,
            'score_adjustment' => $this->scoreAdjustment,
            'actor_id' => $this->actorId,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
