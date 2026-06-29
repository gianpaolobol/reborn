<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

interface PaymentIntentRepository
{
    /** @param array<string, mixed> $metadata */
    public function createMockIntent(RepairOrder $order, string $requestedBy, array $metadata): PaymentIntent;

    public function find(string $id): ?PaymentIntent;

    /** @return list<PaymentIntent> */
    public function listByRepairOrder(string $repairOrderId): array;

    public function confirmMock(string $id): PaymentIntent;
}
