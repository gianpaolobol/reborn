<?php

declare(strict_types=1);

namespace Reborn\Learning\Domain;

final class RepairCompletionReport
{
    /** @param list<string> $evidenceAttachmentIds @param array<string, mixed> $outcome */
    public function __construct(
        public readonly string $id,
        public readonly string $fulfilmentId,
        public readonly string $repairOrderId,
        public readonly string $repairCaseId,
        public readonly string $providerId,
        public readonly string $reportedBy,
        public readonly string $status,
        public readonly string $outcomeStatus,
        public readonly string $functionalResult,
        public readonly bool $customerConfirmed,
        public readonly bool $objectSaved,
        public readonly int $co2AvoidedGrams,
        public readonly array $evidenceAttachmentIds,
        public readonly array $outcome,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $evidence = json_decode((string) ($row['evidence_attachment_ids'] ?? '[]'), true);
        if (!is_array($evidence)) {
            $evidence = [];
        }

        $outcome = json_decode((string) ($row['outcome_json'] ?? '{}'), true);
        if (!is_array($outcome)) {
            $outcome = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['fulfilment_id'],
            (string) $row['repair_order_id'],
            (string) $row['repair_case_id'],
            (string) $row['provider_id'],
            (string) $row['reported_by'],
            (string) $row['status'],
            (string) $row['outcome_status'],
            (string) $row['functional_result'],
            ((int) $row['customer_confirmed']) === 1,
            ((int) $row['object_saved']) === 1,
            (int) $row['co2_avoided_grams'],
            array_values(array_map('strval', $evidence)),
            $outcome,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fulfilment_id' => $this->fulfilmentId,
            'repair_order_id' => $this->repairOrderId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'reported_by' => $this->reportedBy,
            'status' => $this->status,
            'outcome_status' => $this->outcomeStatus,
            'functional_result' => $this->functionalResult,
            'customer_confirmed' => $this->customerConfirmed,
            'object_saved' => $this->objectSaved,
            'co2_avoided_grams' => $this->co2AvoidedGrams,
            'evidence_attachment_ids' => $this->evidenceAttachmentIds,
            'outcome_json' => $this->outcome,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
