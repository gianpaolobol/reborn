<?php

declare(strict_types=1);

namespace Reborn\Trust\Application;

use Reborn\Trust\Domain\ProviderTrustRepository;

final class ListProviderTrustSignalsService
{
    public function __construct(private readonly ProviderTrustRepository $trustRepository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $providerId): array
    {
        return array_map(static fn($signal): array => $signal->toArray(), $this->trustRepository->listSignalsByProvider($providerId));
    }
}
