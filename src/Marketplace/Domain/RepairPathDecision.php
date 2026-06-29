<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Domain;

final class RepairPathDecision
{
    /** @param array<string, mixed> $result */
    public function __construct(
        public readonly string $id,
        public readonly string $repairCaseId,
        public readonly ?string $recognitionJobId,
        public readonly string $requestedBy,
        public readonly string $status,
        public readonly array $result,
        public readonly string $createdAt,
        public readonly ?string $completedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $result = json_decode((string) ($row['result_json'] ?? '{}'), true);
        if (!is_array($result)) {
            $result = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['repair_case_id'],
            $row['recognition_job_id'] !== null ? (string) $row['recognition_job_id'] : null,
            (string) $row['requested_by'],
            (string) $row['status'],
            $result,
            (string) $row['created_at'],
            $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'repair_case_id' => $this->repairCaseId,
            'recognition_job_id' => $this->recognitionJobId,
            'requested_by' => $this->requestedBy,
            'status' => $this->status,
            'result_json' => $this->result,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
