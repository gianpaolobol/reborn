<?php

declare(strict_types=1);

namespace Reborn\Trust\Application;

use Reborn\Identity\Domain\User;
use Reborn\Learning\Domain\RepairCompletionReportRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Trust\Domain\ProviderQualityScoreUpdated;
use Reborn\Trust\Domain\ProviderTrustRepository;
use Reborn\Trust\Domain\ProviderTrustSignalRecorded;
use Reborn\Trust\Domain\TrustReviewRecorded;

final class CreateTrustReviewService
{
    public function __construct(
        private readonly RepairCompletionReportRepository $completionReports,
        private readonly ProviderTrustRepository $trustRepository,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function handle(string $completionReportId, User $reviewer, array $payload): array
    {
        $report = $this->completionReports->find($completionReportId);
        if ($report === null) {
            throw new NotFoundException('Repair completion report not found.');
        }

        $this->validateRatings($payload);
        if ($report->status !== 'recorded') {
            throw new ValidationException(['completion_report' => ['Completion report must be recorded before trust review.']]);
        }

        $review = $this->trustRepository->createReviewFromCompletionReport($report, $reviewer, $payload);
        $scoreDelta = $this->scoreDelta($review->ratingOverall, (bool) ($review->signals['object_saved'] ?? $review->issueResolved), (string) ($review->signals['outcome_status'] ?? 'unknown'));
        $signal = $this->trustRepository->recordSignal($review, 'completion_review_scored', $scoreDelta);
        $qualityScore = $this->trustRepository->recalculateQualityScore($review->providerId, $review->id);

        $now = gmdate('c');
        $this->eventBus->publish(new TrustReviewRecorded($review->id, $review->completionReportId, $review->repairCaseId, $review->providerId, $review->reviewerId, $review->reviewerRole, $review->ratingOverall, $now));
        $this->eventBus->publish(new ProviderTrustSignalRecorded($signal->id, $review->id, $review->providerId, $review->repairCaseId, $signal->eventType, $signal->scoreDelta, $now));
        $this->eventBus->publish(new ProviderQualityScoreUpdated($qualityScore->providerId, $qualityScore->overallScore, $qualityScore->trustTier, $qualityScore->reviewCount, $qualityScore->completedRepairsCount, $now));

        return [
            'trust_review' => $review->toArray(),
            'trust_signal' => $signal->toArray(),
            'quality_score' => $qualityScore->toArray(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function validateRatings(array $payload): void
    {
        $fields = ['rating_overall', 'rating_quality', 'rating_communication', 'rating_timeliness'];
        $errors = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = (int) $payload[$field];
            if ($value < 1 || $value > 5) {
                $errors[$field] = ['Rating must be between 1 and 5.'];
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function scoreDelta(int $ratingOverall, bool $objectSaved, string $outcomeStatus): float
    {
        $base = (($ratingOverall - 3) / 2) * 0.08;
        if ($outcomeStatus === 'successful') {
            $base += 0.04;
        }
        if ($objectSaved) {
            $base += 0.03;
        }
        return round(max(-0.12, min(0.18, $base)), 3);
    }
}
