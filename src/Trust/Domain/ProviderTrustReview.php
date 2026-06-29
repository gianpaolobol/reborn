<?php

declare(strict_types=1);

namespace Reborn\Trust\Domain;

final class ProviderTrustReview
{
    /** @param array<string, mixed> $signals */
    public function __construct(
        public readonly string $id,
        public readonly string $completionReportId,
        public readonly string $fulfilmentId,
        public readonly string $repairCaseId,
        public readonly string $providerId,
        public readonly string $reviewerId,
        public readonly string $reviewerRole,
        public readonly string $status,
        public readonly int $ratingOverall,
        public readonly int $ratingQuality,
        public readonly int $ratingCommunication,
        public readonly int $ratingTimeliness,
        public readonly bool $wouldRecommend,
        public readonly bool $issueResolved,
        public readonly ?string $comment,
        public readonly array $signals,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $signals = json_decode((string) ($row['signals_json'] ?? '{}'), true);
        if (!is_array($signals)) {
            $signals = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['completion_report_id'],
            (string) $row['fulfilment_id'],
            (string) $row['repair_case_id'],
            (string) $row['provider_id'],
            (string) $row['reviewer_id'],
            (string) $row['reviewer_role'],
            (string) $row['status'],
            (int) $row['rating_overall'],
            (int) $row['rating_quality'],
            (int) $row['rating_communication'],
            (int) $row['rating_timeliness'],
            ((int) $row['would_recommend']) === 1,
            ((int) $row['issue_resolved']) === 1,
            $row['comment'] !== null ? (string) $row['comment'] : null,
            $signals,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'completion_report_id' => $this->completionReportId,
            'fulfilment_id' => $this->fulfilmentId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'reviewer_id' => $this->reviewerId,
            'reviewer_role' => $this->reviewerRole,
            'status' => $this->status,
            'rating_overall' => $this->ratingOverall,
            'rating_quality' => $this->ratingQuality,
            'rating_communication' => $this->ratingCommunication,
            'rating_timeliness' => $this->ratingTimeliness,
            'would_recommend' => $this->wouldRecommend,
            'issue_resolved' => $this->issueResolved,
            'comment' => $this->comment,
            'signals_json' => $this->signals,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
