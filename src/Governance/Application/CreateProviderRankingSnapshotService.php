<?php

declare(strict_types=1);

namespace Reborn\Governance\Application;

use Reborn\Governance\Domain\MarketplaceGovernanceRepository;
use Reborn\Governance\Domain\ProviderRankingSnapshotCreated;
use Reborn\Identity\Domain\User;
use Reborn\Shared\Domain\EventBus;

final class CreateProviderRankingSnapshotService
{
    public function __construct(
        private readonly ProviderRankingEngine $rankingEngine,
        private readonly MarketplaceGovernanceRepository $governanceRepository,
        private readonly MarketplaceGovernancePolicy $policy,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(User $actor): array
    {
        $ranking = $this->rankingEngine->rank();
        $policy = $this->policy->toArray();
        $snapshot = $this->governanceRepository->createRankingSnapshot($actor, $ranking, $policy, ProviderRankingEngine::FORMULA_VERSION);
        $this->governanceRepository->audit($actor, 'provider_ranking_snapshot_created', 'provider_ranking_snapshot', $snapshot->id, [
            'provider_count' => $snapshot->providerCount,
            'formula_version' => $snapshot->rankingFormulaVersion,
        ]);
        $this->eventBus->publish(new ProviderRankingSnapshotCreated($snapshot->id, $snapshot->providerCount, $snapshot->rankingFormulaVersion, $actor->id, gmdate('c')));

        return ['ranking_snapshot' => $snapshot->toArray(), 'provider_rankings' => $snapshot->ranking];
    }
}
