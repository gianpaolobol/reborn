<?php

declare(strict_types=1);

namespace Reborn\Learning\Application;

use Reborn\Learning\Domain\RepairCompletionReportRepository;

final class ListCompletionReportsService
{
    public function __construct(private readonly RepairCompletionReportRepository $reports)
    {
    }

    /** @return list<array<string, mixed>> */
    public function handle(string $fulfilmentId): array
    {
        return array_map(static fn($report): array => $report->toArray(), $this->reports->listByFulfilment($fulfilmentId));
    }
}
