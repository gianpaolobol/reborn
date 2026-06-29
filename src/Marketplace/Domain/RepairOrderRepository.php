<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

interface RepairOrderRepository
{
    /** @param array<string, mixed> $order */
    public function createFromQuote(
        string $quoteRequestId,
        string $providerMatchId,
        string $repairCaseId,
        string $providerId,
        string $orderedBy,
        string $currency,
        int $subtotalCents,
        int $platformFeeCents,
        int $providerPayoutCents,
        int $totalCents,
        array $order
    ): RepairOrder;

    public function find(string $id): ?RepairOrder;

    /** @return list<RepairOrder> */
    public function listByRepairCase(string $repairCaseId): array;
}
