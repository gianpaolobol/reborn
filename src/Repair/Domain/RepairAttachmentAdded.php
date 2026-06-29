<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class RepairAttachmentAdded implements DomainEvent
{
    public function __construct(
        private readonly string $repairCaseId,
        private readonly string $attachmentId,
        private readonly string $kind,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'repair.attachment_added';
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'attachment_id' => $this->attachmentId,
            'kind' => $this->kind,
        ];
    }
}
