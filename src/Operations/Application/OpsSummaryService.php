<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Operations\Domain\AdminOperationsRepository;

final class OpsSummaryService
{
    public function __construct(private readonly AdminOperationsRepository $repository, private readonly AdminOperationsPolicy $policy)
    {
    }

    public function handle(): array
    {
        return ['summary' => $this->repository->summary(), 'policy' => $this->policy->toArray()];
    }
}
