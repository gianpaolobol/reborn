<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Application;

use Reborn\Fulfilment\Domain\FulfilmentStatusUpdated;
use Reborn\Fulfilment\Domain\RepairFulfilmentRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class UpdateFulfilmentStatusService
{
    private const ALLOWED = ['accepted', 'in_progress', 'quality_check', 'ready_to_ship', 'completed', 'rejected'];

    public function __construct(
        private readonly RepairFulfilmentRepository $fulfilments,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{fulfilment: array<string, mixed>} */
    public function handle(string $fulfilmentId, string $status, ?string $note, string $actorId): array
    {
        $current = $this->fulfilments->find($fulfilmentId);
        if ($current === null) {
            throw new NotFoundException('Repair fulfilment not found.');
        }
        if (!in_array($status, self::ALLOWED, true)) {
            throw new ValidationException(['status' => ['Invalid fulfilment status.']]);
        }
        if ($current->status === 'awaiting_provider_acceptance' && $status !== 'accepted') {
            throw new ValidationException(['status' => ['Provider must accept the fulfilment before operational status updates.']]);
        }

        $fulfilment = $this->fulfilments->updateStatus($fulfilmentId, $status, $note, $actorId);
        $this->eventBus->publish(new FulfilmentStatusUpdated($fulfilment->id, $fulfilment->repairOrderId, $fulfilment->repairCaseId, $fulfilment->status, $actorId, gmdate('c')));

        return ['fulfilment' => $fulfilment->toArray()];
    }
}
