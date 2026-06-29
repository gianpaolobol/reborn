<?php

declare(strict_types=1);

namespace Reborn\Trust\Domain;

final class ProviderTrustSignal
{
    /** @param array<string, mixed> $signal */
    public function __construct(
        public readonly string $id,
        public readonly string $providerId,
        public readonly string $repairCaseId,
        public readonly string $completionReportId,
        public readonly string $trustReviewId,
        public readonly string $eventType,
        public readonly array $signal,
        public readonly float $scoreDelta,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $signal = json_decode((string) ($row['signal_json'] ?? '{}'), true);
        if (!is_array($signal)) {
            $signal = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['provider_id'],
            (string) $row['repair_case_id'],
            (string) $row['completion_report_id'],
            (string) $row['trust_review_id'],
            (string) $row['event_type'],
            $signal,
            (float) $row['score_delta'],
            (string) $row['created_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->providerId,
            'repair_case_id' => $this->repairCaseId,
            'completion_report_id' => $this->completionReportId,
            'trust_review_id' => $this->trustReviewId,
            'event_type' => $this->eventType,
            'signal_json' => $this->signal,
            'score_delta' => $this->scoreDelta,
            'created_at' => $this->createdAt,
        ];
    }
}
