<?php

declare(strict_types=1);

namespace Reborn\Trust\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class TrustReviewRecorded implements DomainEvent
{
    public function __construct(
        private readonly string $trustReviewId,
        private readonly string $completionReportId,
        private readonly string $repairCaseId,
        private readonly string $providerId,
        private readonly string $reviewerId,
        private readonly string $reviewerRole,
        private readonly int $ratingOverall,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'trust.review_recorded';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'trust_review_id' => $this->trustReviewId,
            'completion_report_id' => $this->completionReportId,
            'repair_case_id' => $this->repairCaseId,
            'provider_id' => $this->providerId,
            'reviewer_id' => $this->reviewerId,
            'reviewer_role' => $this->reviewerRole,
            'rating_overall' => $this->ratingOverall,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
