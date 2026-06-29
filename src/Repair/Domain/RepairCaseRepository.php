<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

interface RepairCaseRepository
{
    /** @return list<RepairCase> */
    public function list(int $limit = 50, ?string $ownerId = null): array;

    public function find(string $id): ?RepairCase;

    /** @param array<string, mixed> $data */
    public function create(array $data): RepairCase;

    /** @param array<string, mixed> $diagnosis */
    public function updateDiagnosis(string $id, array $diagnosis): RepairCase;
}
