<?php

declare(strict_types=1);

namespace Reborn\Learning\Domain;

use Reborn\Fulfilment\Domain\RepairFulfilment;

interface RepairCompletionReportRepository
{
    /** @param array<string, mixed> $payload */
    public function createFromFulfilment(RepairFulfilment $fulfilment, string $reportedBy, array $payload): RepairCompletionReport;

    public function find(string $id): ?RepairCompletionReport;

    /** @return list<RepairCompletionReport> */
    public function listByFulfilment(string $fulfilmentId): array;
}
