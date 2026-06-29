<?php

declare(strict_types=1);

namespace Reborn\Governance\Domain;

use Reborn\Identity\Domain\User;

interface MarketplaceGovernanceRepository
{
    /** @param array<string, mixed> $payload */
    public function recordProviderAction(string $providerId, User $actor, array $payload): ProviderGovernanceAction;

    /** @return list<ProviderGovernanceAction> */
    public function listProviderActions(string $providerId, bool $activeOnly = false): array;

    /** @return list<ProviderGovernanceAction> */
    public function listActions(?string $status = null): array;

    /** @param list<array<string, mixed>> $ranking @param array<string, mixed> $policy */
    public function createRankingSnapshot(User $actor, array $ranking, array $policy, string $formulaVersion): ProviderRankingSnapshot;

    public function latestRankingSnapshot(): ?ProviderRankingSnapshot;

    /** @return list<array<string, mixed>> */
    public function currentProviderRankings(): array;

    /** @param array<string, mixed> $payload */
    public function audit(User $actor, string $action, string $subjectType, string $subjectId, array $payload): void;

    /** @return array<string, mixed> */
    public function summary(): array;
}
