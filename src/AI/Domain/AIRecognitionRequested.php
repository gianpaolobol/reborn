<?php

declare(strict_types=1);

namespace Reborn\AI\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class AIRecognitionRequested implements DomainEvent
{
    /** @param list<string> $attachmentIds */
    public function __construct(
        private readonly string $repairCaseId,
        private readonly string $recognitionJobId,
        private readonly string $requestedBy,
        private readonly array $attachmentIds,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'ai.recognition_requested';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'recognition_job_id' => $this->recognitionJobId,
            'requested_by' => $this->requestedBy,
            'attachment_ids' => $this->attachmentIds,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
