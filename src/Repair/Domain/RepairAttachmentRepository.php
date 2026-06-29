<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

interface RepairAttachmentRepository
{
    /** @return list<RepairAttachment> */
    public function listByRepairCase(string $repairCaseId): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): RepairAttachment;
}
