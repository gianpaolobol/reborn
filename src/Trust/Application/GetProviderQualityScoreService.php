<?php

declare(strict_types=1);

namespace Reborn\Trust\Application;

use Reborn\Trust\Domain\ProviderTrustRepository;

final class GetProviderQualityScoreService
{
    public function __construct(private readonly ProviderTrustRepository $trustRepository)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $providerId): ?array
    {
        $score = $this->trustRepository->findQualityScore($providerId);
        return $score?->toArray();
    }
}
