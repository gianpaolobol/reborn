<?php

declare(strict_types=1);

namespace Reborn\AI\Domain;

interface RecognitionJobRepository
{
    /** @param list<string> $attachmentIds */
    public function create(string $repairCaseId, string $requestedBy, array $attachmentIds): RecognitionJob;

    public function markProcessing(string $id): RecognitionJob;

    /** @param array<string, mixed> $result */
    public function complete(string $id, array $result): RecognitionJob;

    public function fail(string $id, string $message): RecognitionJob;

    public function find(string $id): ?RecognitionJob;

    /** @return list<RecognitionJob> */
    public function listByRepairCase(string $repairCaseId): array;
}
