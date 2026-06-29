<?php

declare(strict_types=1);

namespace Reborn\Governance\Application;

use Reborn\Governance\Domain\MarketplaceGovernanceRepository;

final class GovernanceSummaryService
{
    public function __construct(
        private readonly MarketplaceGovernanceRepository $governanceRepository,
        private readonly MarketplaceGovernancePolicy $policy,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'summary' => $this->governanceRepository->summary(),
            'policy' => $this->policy->toArray(),
        ];
    }
}
