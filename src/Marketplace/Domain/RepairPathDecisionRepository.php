<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

interface RepairPathDecisionRepository
{
    /** @param array<string, mixed> $result */
    public function createCompleted(string $repairCaseId, ?string $recognitionJobId, string $requestedBy, array $result): RepairPathDecision;

    public function find(string $id): ?RepairPathDecision;

    /** @return list<RepairPathDecision> */
    public function listByRepairCase(string $repairCaseId): array;
}
