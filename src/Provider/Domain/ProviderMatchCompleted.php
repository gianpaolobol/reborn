<?php

declare(strict_types=1);

namespace Reborn\Provider\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderMatchCompleted implements DomainEvent
{
    public function __construct(
        private readonly string $repairCaseId,
        private readonly string $providerMatchId,
        private readonly ?string $topProviderId,
        private readonly int $matchCount,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'provider.match_completed';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'provider_match_id' => $this->providerMatchId,
            'top_provider_id' => $this->topProviderId,
            'match_count' => $this->matchCount,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
