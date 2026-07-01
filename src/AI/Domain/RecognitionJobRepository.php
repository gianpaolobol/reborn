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

    /**
     * Step 49.9: reuse the most recent successful live Vision result for the
     * same uploaded image hash before spending another provider call.
     *
     * @param list<string> $sha256s
     */
    public function findReusableLiveResultByAttachmentSha256(array $sha256s): ?RecognitionJob;
}
