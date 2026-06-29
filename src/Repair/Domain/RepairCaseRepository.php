<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

interface RepairCaseRepository
{
    /** @return list<RepairCase> */
    public function list(int $limit = 50): array;

    public function find(string $id): ?RepairCase;

    public function create(array $data): RepairCase;

    public function updateDiagnosis(string $id, array $diagnosis): RepairCase;
}
