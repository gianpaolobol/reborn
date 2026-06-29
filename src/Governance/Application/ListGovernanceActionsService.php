<?php

declare(strict_types=1);

namespace Reborn\Governance\Application;

use Reborn\Governance\Domain\MarketplaceGovernanceRepository;

final class ListGovernanceActionsService
{
    public function __construct(private readonly MarketplaceGovernanceRepository $governanceRepository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function forProvider(string $providerId, bool $activeOnly = false): array
    {
        return array_map(static fn($action): array => $action->toArray(), $this->governanceRepository->listProviderActions($providerId, $activeOnly));
    }

    /** @return list<array<string, mixed>> */
    public function all(?string $status = null): array
    {
        return array_map(static fn($action): array => $action->toArray(), $this->governanceRepository->listActions($status));
    }
}
