<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use Reborn\Provider\Domain\ProviderQuoteRequestRepository;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Http\NotFoundException;

final class ListProviderQuoteRequestsService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly ProviderQuoteRequestRepository $quotes,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairCaseId): array
    {
        if ($this->repairCases->find($repairCaseId) === null) {
            throw new NotFoundException('Repair case not found.');
        }

        return array_map(static fn($quote): array => $quote->toArray(), $this->quotes->listByRepairCase($repairCaseId));
    }
}
