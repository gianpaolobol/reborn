<?php

declare(strict_types=1);

namespace Reborn\Provider\Domain;

interface ProviderQuoteRequestRepository
{
    /** @param array<string, mixed> $quote */
    public function createEstimated(string $providerMatchId, string $repairCaseId, string $providerId, string $requestedBy, array $quote, string $expiresAt): ProviderQuoteRequest;

    public function find(string $id): ?ProviderQuoteRequest;

    /** @return list<ProviderQuoteRequest> */
    public function listByRepairCase(string $repairCaseId): array;
}
