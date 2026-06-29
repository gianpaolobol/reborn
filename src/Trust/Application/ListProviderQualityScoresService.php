<?php

declare(strict_types=1);

namespace Reborn\Trust\Application;

use Reborn\Trust\Domain\ProviderTrustRepository;

final class ListProviderQualityScoresService
{
    public function __construct(private readonly ProviderTrustRepository $trustRepository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return array_map(static fn($score): array => $score->toArray(), $this->trustRepository->listQualityScores());
    }
}
