<?php

declare(strict_types=1);

namespace Reborn\Provider\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class ProviderMatchRequested implements DomainEvent
{
    public function __construct(
        private readonly string $repairCaseId,
        private readonly ?string $repairPathDecisionId,
        private readonly string $requestedBy,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'provider.match_requested';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'repair_path_decision_id' => $this->repairPathDecisionId,
            'requested_by' => $this->requestedBy,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
