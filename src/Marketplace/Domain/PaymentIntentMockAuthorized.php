<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class PaymentIntentMockAuthorized implements DomainEvent
{
    public function __construct(
        private readonly string $paymentIntentId,
        private readonly string $repairOrderId,
        private readonly string $repairCaseId,
        private readonly int $amountCents,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'payment.intent_mock_authorized';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId,
            'repair_order_id' => $this->repairOrderId,
            'repair_case_id' => $this->repairCaseId,
            'amount_cents' => $this->amountCents,
            'currency' => 'EUR',
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
