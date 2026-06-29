<?php

declare(strict_types=1);

namespace Reborn\AI\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class AIRecognitionCompleted implements DomainEvent
{
    public function __construct(
        private readonly string $repairCaseId,
        private readonly string $recognitionJobId,
        private readonly float $confidence,
        private readonly string $recommendedPath,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'ai.recognition_completed';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'repair_case_id' => $this->repairCaseId,
            'recognition_job_id' => $this->recognitionJobId,
            'confidence' => $this->confidence,
            'recommended_path' => $this->recommendedPath,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
