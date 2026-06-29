<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Application;

use Reborn\Fulfilment\Domain\FulfilmentRequested;
use Reborn\Fulfilment\Domain\RepairFulfilmentRepository;
use Reborn\Marketplace\Domain\PaymentIntentRepository;
use Reborn\Marketplace\Domain\RepairOrderRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class CreateRepairFulfilmentService
{
    public function __construct(
        private readonly RepairOrderRepository $orders,
        private readonly PaymentIntentRepository $paymentIntents,
        private readonly RepairFulfilmentRepository $fulfilments,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{fulfilment: array<string, mixed>} */
    public function handle(string $repairOrderId, string $requestedBy): array
    {
        $order = $this->orders->find($repairOrderId);
        if ($order === null) {
            throw new NotFoundException('Repair order not found.');
        }

        $intents = $this->paymentIntents->listByRepairOrder($repairOrderId);
        $authorized = array_values(array_filter($intents, static fn($intent): bool => $intent->status === 'mock_authorized'));
        if ($authorized === []) {
            throw new ValidationException(['repair_order_id' => ['Repair fulfilment requires a mock-authorized payment intent.']]);
        }

        $fulfilment = $this->fulfilments->createFromRepairOrder($order, $requestedBy);
        $this->eventBus->publish(new FulfilmentRequested($fulfilment->id, $fulfilment->repairOrderId, $fulfilment->repairCaseId, $fulfilment->providerId, $requestedBy, gmdate('c')));

        return ['fulfilment' => $fulfilment->toArray()];
    }
}
