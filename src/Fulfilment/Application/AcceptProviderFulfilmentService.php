<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Application;

use Reborn\Fulfilment\Domain\FulfilmentProviderAccepted;
use Reborn\Fulfilment\Domain\RepairFulfilmentRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class AcceptProviderFulfilmentService
{
    public function __construct(
        private readonly RepairFulfilmentRepository $fulfilments,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{fulfilment: array<string, mixed>} */
    public function handle(string $fulfilmentId, string $acceptedBy, ?string $providerNotes = null): array
    {
        $current = $this->fulfilments->find($fulfilmentId);
        if ($current === null) {
            throw new NotFoundException('Repair fulfilment not found.');
        }
        if ($current->status !== 'awaiting_provider_acceptance') {
            throw new ValidationException(['fulfilment_id' => ['Fulfilment is not awaiting provider acceptance.']]);
        }

        $fulfilment = $this->fulfilments->acceptProvider($fulfilmentId, $acceptedBy, $providerNotes);
        $this->eventBus->publish(new FulfilmentProviderAccepted($fulfilment->id, $fulfilment->repairOrderId, $fulfilment->repairCaseId, $fulfilment->providerId, $acceptedBy, gmdate('c')));

        return ['fulfilment' => $fulfilment->toArray()];
    }
}
