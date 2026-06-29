<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\AI\Domain\RecognitionJobRepository;

final class GetRecognitionJobService
{
    public function __construct(private readonly RecognitionJobRepository $recognitionJobs)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $job = $this->recognitionJobs->find($id);
        return $job?->toArray();
    }
}
