<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\PaymentIntentRepository;

final class GetPaymentIntentService
{
    public function __construct(private readonly PaymentIntentRepository $paymentIntents)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->paymentIntents->find($id)?->toArray();
    }
}
