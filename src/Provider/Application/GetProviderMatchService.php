<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use Reborn\Provider\Domain\ProviderMatchRepository;

final class GetProviderMatchService
{
    public function __construct(private readonly ProviderMatchRepository $providerMatches)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->providerMatches->find($id)?->toArray();
    }
}
