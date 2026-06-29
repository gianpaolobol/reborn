<?php

declare(strict_types=1);

namespace Reborn\Repair\Application;

use Reborn\Repair\Domain\RepairAttachmentRepository;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Http\NotFoundException;

final class ListRepairAttachmentsService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RepairAttachmentRepository $attachments,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairCaseId): array
    {
        if ($this->repairCases->find($repairCaseId) === null) {
            throw new NotFoundException('Repair case not found.');
        }

        return array_map(
            static fn($attachment): array => $attachment->toArray(),
            $this->attachments->listByRepairCase($repairCaseId)
        );
    }
}
