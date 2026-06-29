<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Operations\Domain\AdminOperationsRepository;

final class ListOpsEscalationsService
{
    public function __construct(private readonly AdminOperationsRepository $repository)
    {
    }

    public function handle(?string $status = null): array
    {
        return ['escalations' => array_map(static fn($escalation): array => $escalation->toArray(), $this->repository->listEscalations($status))];
    }
}
