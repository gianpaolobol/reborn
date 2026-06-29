<?php

declare(strict_types=1);

namespace Reborn\Trust\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderQualityScoreUpdated implements DomainEvent
{
    public function __construct(
        private readonly string $providerId,
        private readonly float $overallScore,
        private readonly string $trustTier,
        private readonly int $reviewCount,
        private readonly int $completedRepairsCount,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'provider.quality_score_updated';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'provider_id' => $this->providerId,
            'overall_score' => $this->overallScore,
            'trust_tier' => $this->trustTier,
            'review_count' => $this->reviewCount,
            'completed_repairs_count' => $this->completedRepairsCount,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
