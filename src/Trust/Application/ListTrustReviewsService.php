<?php

declare(strict_types=1);

namespace Reborn\Trust\Application;

use Reborn\Trust\Domain\ProviderTrustRepository;

final class ListTrustReviewsService
{
    public function __construct(private readonly ProviderTrustRepository $trustRepository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function forCompletionReport(string $completionReportId): array
    {
        return array_map(static fn($review): array => $review->toArray(), $this->trustRepository->listReviewsByCompletionReport($completionReportId));
    }

    /** @return list<array<string, mixed>> */
    public function forProvider(string $providerId): array
    {
        return array_map(static fn($review): array => $review->toArray(), $this->trustRepository->listReviewsByProvider($providerId));
    }
}
