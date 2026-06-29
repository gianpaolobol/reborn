<?php

declare(strict_types=1);

namespace Reborn\Learning\Domain;

interface RepairLearningEventRepository
{
    /** @param array<string, mixed> $signal */
    public function record(RepairCompletionReport $report, string $eventType, array $signal, float $confidenceDelta): RepairLearningEvent;

    public function find(string $id): ?RepairLearningEvent;

    /** @return list<RepairLearningEvent> */
    public function listByRepairCase(string $repairCaseId): array;
}
