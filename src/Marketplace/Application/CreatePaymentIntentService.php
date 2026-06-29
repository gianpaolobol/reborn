<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\PaymentIntentCreated;
use Reborn\Marketplace\Domain\PaymentIntentRepository;
use Reborn\Marketplace\Domain\RepairOrderRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class CreatePaymentIntentService
{
    public function __construct(
        private readonly RepairOrderRepository $orders,
        private readonly PaymentIntentRepository $paymentIntents,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{payment_intent: array<string, mixed>} */
    public function handle(string $repairOrderId, string $requestedBy): array
    {
        $order = $this->orders->find($repairOrderId);
        if ($order === null) {
            throw new NotFoundException('Repair order not found.');
        }
        if ($order->status !== 'created') {
            throw new ValidationException(['repair_order_id' => ['Repair order must be created before requesting payment.']]);
        }
        if ($order->totalCents <= 0) {
            throw new ValidationException(['repair_order_id' => ['Repair order total must be greater than zero.']]);
        }

        $metadata = [
            'repair_order_id' => $order->id,
            'quote_request_id' => $order->quoteRequestId,
            'repair_case_id' => $order->repairCaseId,
            'provider_id' => $order->providerId,
            'platform_fee_cents' => $order->platformFeeCents,
            'provider_payout_cents' => $order->providerPayoutCents,
            'real_payment_provider' => null,
            'mvp_note' => 'Mock payment intent only. No real money movement occurs in Step 14.',
        ];

        $intent = $this->paymentIntents->createMockIntent($order, $requestedBy, $metadata);
        $this->eventBus->publish(new PaymentIntentCreated($intent->id, $order->id, $order->repairCaseId, $requestedBy, $intent->amountCents, $intent->provider, gmdate('c')));

        return ['payment_intent' => $intent->toArray()];
    }
}
