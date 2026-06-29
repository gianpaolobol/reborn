<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\PaymentIntentMockAuthorized;
use Reborn\Marketplace\Domain\PaymentIntentRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class ConfirmMockPaymentIntentService
{
    public function __construct(
        private readonly PaymentIntentRepository $paymentIntents,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{payment_intent: array<string, mixed>} */
    public function handle(string $paymentIntentId): array
    {
        $current = $this->paymentIntents->find($paymentIntentId);
        if ($current === null) {
            throw new NotFoundException('Payment intent not found.');
        }
        if ($current->status !== 'requires_mock_confirmation') {
            throw new ValidationException(['payment_intent_id' => ['Payment intent is not awaiting mock confirmation.']]);
        }

        $intent = $this->paymentIntents->confirmMock($paymentIntentId);
        $this->eventBus->publish(new PaymentIntentMockAuthorized($intent->id, $intent->repairOrderId, $intent->repairCaseId, $intent->amountCents, gmdate('c')));

        return ['payment_intent' => $intent->toArray()];
    }
}
