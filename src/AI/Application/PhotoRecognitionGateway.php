<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairAttachment;
use Reborn\Repair\Domain\RepairCase;

interface PhotoRecognitionGateway
{
    /** @return array<string, mixed> */
    public function status(): array;

    /**
     * @param list<RepairAttachment> $attachments
     * @return array<string, mixed>|null Null means the caller should use the deterministic local fallback.
     */
    public function analyze(RepairCase $case, array $attachments): ?array;
}
