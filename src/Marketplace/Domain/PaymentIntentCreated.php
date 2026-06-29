<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class PaymentIntentCreated implements DomainEvent
{
    public function __construct(
        private readonly string $paymentIntentId,
        private readonly string $repairOrderId,
        private readonly string $repairCaseId,
        private readonly string $requestedBy,
        private readonly int $amountCents,
        private readonly string $provider,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'payment.intent_created';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId,
            'repair_order_id' => $this->repairOrderId,
            'repair_case_id' => $this->repairCaseId,
            'requested_by' => $this->requestedBy,
            'amount_cents' => $this->amountCents,
            'currency' => 'EUR',
            'provider' => $this->provider,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
