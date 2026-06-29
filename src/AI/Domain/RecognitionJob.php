<?php

declare(strict_types=1);

namespace Reborn\AI\Domain;

final class RecognitionJob
{
    /**
     * @param list<string> $inputAttachmentIds
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        public readonly string $id,
        public readonly string $repairCaseId,
        public readonly string $requestedBy,
        public readonly string $status,
        public readonly array $inputAttachmentIds,
        public readonly ?array $result,
        public readonly ?string $errorMessage,
        public readonly string $createdAt,
        public readonly ?string $startedAt,
        public readonly ?string $completedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $inputIds = json_decode((string) ($row['input_attachment_ids'] ?? '[]'), true);
        if (!is_array($inputIds)) {
            $inputIds = [];
        }

        $result = null;
        if (($row['result_json'] ?? null) !== null && (string) $row['result_json'] !== '') {
            $decoded = json_decode((string) $row['result_json'], true);
            $result = is_array($decoded) ? $decoded : null;
        }

        return new self(
            (string) $row['id'],
            (string) $row['repair_case_id'],
            (string) $row['requested_by'],
            (string) $row['status'],
            array_values(array_map(static fn($value): string => (string) $value, $inputIds)),
            $result,
            $row['error_message'] !== null ? (string) $row['error_message'] : null,
            (string) $row['created_at'],
            $row['started_at'] !== null ? (string) $row['started_at'] : null,
            $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'repair_case_id' => $this->repairCaseId,
            'requested_by' => $this->requestedBy,
            'status' => $this->status,
            'input_attachment_ids' => $this->inputAttachmentIds,
            'result_json' => $this->result,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
