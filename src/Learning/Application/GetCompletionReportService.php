<?php

declare(strict_types=1);

namespace Reborn\Learning\Application;

use Reborn\Learning\Domain\RepairCompletionReportRepository;

final class GetCompletionReportService
{
    public function __construct(private readonly RepairCompletionReportRepository $reports)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->reports->find($id)?->toArray();
    }
}
