<?php

declare(strict_types=1);

namespace Reborn\Trust\Domain;

use Reborn\Identity\Domain\User;
use Reborn\Learning\Domain\RepairCompletionReport;

interface ProviderTrustRepository
{
    /** @param array<string, mixed> $payload */
    public function createReviewFromCompletionReport(RepairCompletionReport $report, User $reviewer, array $payload): ProviderTrustReview;

    public function recordSignal(ProviderTrustReview $review, string $eventType, float $scoreDelta): ProviderTrustSignal;

    public function recalculateQualityScore(string $providerId, ?string $lastReviewId = null): ProviderQualityScore;

    public function findReview(string $id): ?ProviderTrustReview;

    /** @return list<ProviderTrustReview> */
    public function listReviewsByCompletionReport(string $completionReportId): array;

    /** @return list<ProviderTrustReview> */
    public function listReviewsByProvider(string $providerId): array;

    public function findQualityScore(string $providerId): ?ProviderQualityScore;

    /** @return list<ProviderQualityScore> */
    public function listQualityScores(): array;

    /** @return list<ProviderTrustSignal> */
    public function listSignalsByProvider(string $providerId): array;
}
