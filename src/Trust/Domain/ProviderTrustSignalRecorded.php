<?php

declare(strict_types=1);

namespace Reborn\Trust\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderTrustSignalRecorded implements DomainEvent
{
    public function __construct(
        private readonly string $trustSignalId,
        private readonly string $trustReviewId,
        private readonly string $providerId,
        private readonly string $repairCaseId,
        private readonly string $eventType,
        private readonly float $scoreDelta,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'provider.trust_signal_recorded';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'trust_signal_id' => $this->trustSignalId,
            'trust_review_id' => $this->trustReviewId,
            'provider_id' => $this->providerId,
            'repair_case_id' => $this->repairCaseId,
            'event_type' => $this->eventType,
            'score_delta' => $this->scoreDelta,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
