<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use Reborn\Provider\Domain\ProviderQuoteRequestRepository;

final class GetProviderQuoteRequestService
{
    public function __construct(private readonly ProviderQuoteRequestRepository $quotes)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->quotes->find($id)?->toArray();
    }
}
