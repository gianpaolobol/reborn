<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Infrastructure;

interface MarketplaceModelSearchRepository
{
    /** @param array<string, mixed> $queryJson */
    public function create(string $repairCaseId, string $recognitionJobId, array $queryJson): string;

    /** @param array<string, mixed> $candidate */
    public function addCandidate(string $searchId, array $candidate): void;

    /** @param array<string, mixed> $summary */
    public function complete(string $searchId, string $status, array $summary): void;
}
