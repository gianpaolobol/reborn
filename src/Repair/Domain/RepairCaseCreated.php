<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class RepairCaseCreated implements DomainEvent
{
    public function __construct(private readonly string $repairCaseId, private readonly string $category, private readonly string $occurredAt)
    {
    }

    public function name(): string
    {
        return 'repair.case.created';
    }

    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'category' => $this->category,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
