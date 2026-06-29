<?php

declare(strict_types=1);

namespace Reborn\Provider\Domain;

interface ProviderMatchRepository
{
    /** @param array<string, mixed> $result */
    public function createCompleted(string $repairCaseId, ?string $repairPathDecisionId, string $requestedBy, array $result): ProviderMatch;

    public function find(string $id): ?ProviderMatch;

    /** @return list<ProviderMatch> */
    public function listByRepairCase(string $repairCaseId): array;
}
