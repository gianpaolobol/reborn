<?php

declare(strict_types=1);

namespace Reborn\Governance\Application;

use Reborn\Governance\Domain\MarketplaceGovernanceRepository;

final class ListProviderRankingsService
{
    public function __construct(private readonly MarketplaceGovernanceRepository $governanceRepository)
    {
    }

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $snapshot = $this->governanceRepository->latestRankingSnapshot();
        return [
            'ranking_snapshot' => $snapshot?->toArray(),
            'provider_rankings' => $snapshot?->ranking ?? [],
        ];
    }
}
