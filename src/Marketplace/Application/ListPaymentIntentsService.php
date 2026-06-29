<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\PaymentIntentRepository;
use Reborn\Marketplace\Domain\RepairOrderRepository;
use Reborn\Shared\Http\NotFoundException;

final class ListPaymentIntentsService
{
    public function __construct(
        private readonly RepairOrderRepository $orders,
        private readonly PaymentIntentRepository $paymentIntents,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $repairOrderId): array
    {
        if ($this->orders->find($repairOrderId) === null) {
            throw new NotFoundException('Repair order not found.');
        }

        return array_map(static fn($intent): array => $intent->toArray(), $this->paymentIntents->listByRepairOrder($repairOrderId));
    }
}
