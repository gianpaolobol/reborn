<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\RepairPathDecisionRepository;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Http\NotFoundException;

final class ListRepairPathDecisionsService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RepairPathDecisionRepository $decisions,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairCaseId): array
    {
        if ($this->repairCases->find($repairCaseId) === null) {
            throw new NotFoundException('Repair case not found.');
        }

        return array_map(
            static fn($decision): array => $decision->toArray(),
            $this->decisions->listByRepairCase($repairCaseId)
        );
    }
}
