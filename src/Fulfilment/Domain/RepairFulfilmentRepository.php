<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Domain;

use Reborn\Marketplace\Domain\RepairOrder;

interface RepairFulfilmentRepository
{
    public function createFromRepairOrder(RepairOrder $order, string $requestedBy): RepairFulfilment;

    public function find(string $id): ?RepairFulfilment;

    /** @return list<RepairFulfilment> */
    public function listByRepairOrder(string $repairOrderId): array;

    public function acceptProvider(string $id, string $acceptedBy, ?string $providerNotes): RepairFulfilment;

    public function updateStatus(string $id, string $status, ?string $note, string $actorId): RepairFulfilment;
}
