<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\AI\Domain\RecognitionJobRepository;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Http\NotFoundException;

final class ListRecognitionJobsService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RecognitionJobRepository $recognitionJobs,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairCaseId): array
    {
        if ($this->repairCases->find($repairCaseId) === null) {
            throw new NotFoundException('Repair case not found.');
        }

        return array_map(
            static fn($job): array => $job->toArray(),
            $this->recognitionJobs->listByRepairCase($repairCaseId)
        );
    }
}
