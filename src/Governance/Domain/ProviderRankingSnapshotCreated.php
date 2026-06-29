<?php

declare(strict_types=1);

namespace Reborn\Governance\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderRankingSnapshotCreated implements DomainEvent
{
    public function __construct(
        private readonly string $snapshotId,
        private readonly int $providerCount,
        private readonly string $formulaVersion,
        private readonly string $actorId,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'governance.provider_ranking_snapshot_created';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'snapshot_id' => $this->snapshotId,
            'provider_count' => $this->providerCount,
            'ranking_formula_version' => $this->formulaVersion,
            'actor_id' => $this->actorId,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
