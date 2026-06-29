<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Marketplace\Domain\RepairOrderCreated;
use Reborn\Marketplace\Domain\RepairOrderRepository;
use Reborn\Provider\Domain\ProviderQuoteRequestRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class CreateRepairOrderService
{
    public function __construct(
        private readonly ProviderQuoteRequestRepository $quotes,
        private readonly RepairOrderRepository $orders,
        private readonly RepairOrderAssembler $assembler,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{repair_order: array<string, mixed>} */
    public function handle(string $quoteRequestId, string $orderedBy): array
    {
        $quote = $this->quotes->find($quoteRequestId);
        if ($quote === null) {
            throw new NotFoundException('Quote request not found.');
        }
        if ($quote->status !== 'estimated') {
            throw new ValidationException(['quote_request_id' => ['Quote must be estimated before creating a repair order.']]);
        }
        if ($quote->acceptedAt !== null) {
            throw new ValidationException(['quote_request_id' => ['Quote request has already been accepted.']]);
        }

        $assembled = $this->assembler->assemble($quote);
        if ((int) $assembled['total_cents'] <= 0) {
            throw new ValidationException(['quote_request_id' => ['Quote total must be greater than zero before order creation.']]);
        }

        $order = $this->orders->createFromQuote(
            $quote->id,
            $quote->providerMatchId,
            $quote->repairCaseId,
            $quote->providerId,
            $orderedBy,
            (string) $assembled['currency'],
            (int) $assembled['subtotal_cents'],
            (int) $assembled['platform_fee_cents'],
            (int) $assembled['provider_payout_cents'],
            (int) $assembled['total_cents'],
            $assembled['order_json']
        );

        $this->eventBus->publish(new RepairOrderCreated(
            $order->id,
            $quote->id,
            $quote->repairCaseId,
            $quote->providerId,
            $orderedBy,
            $order->totalCents,
            gmdate('c')
        ));

        return ['repair_order' => $order->toArray()];
    }
}
