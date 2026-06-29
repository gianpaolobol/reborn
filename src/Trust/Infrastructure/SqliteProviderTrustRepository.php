<?php

declare(strict_types=1);

namespace Reborn\Trust\Infrastructure;

use PDO;
use Reborn\Identity\Domain\User;
use Reborn\Learning\Domain\RepairCompletionReport;
use Reborn\Shared\Support\Uuid;
use Reborn\Trust\Domain\ProviderQualityScore;
use Reborn\Trust\Domain\ProviderTrustRepository;
use Reborn\Trust\Domain\ProviderTrustReview;
use Reborn\Trust\Domain\ProviderTrustSignal;

final class SqliteProviderTrustRepository implements ProviderTrustRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $payload */
    public function createReviewFromCompletionReport(RepairCompletionReport $report, User $reviewer, array $payload): ProviderTrustReview
    {
        $existing = $this->findReviewByReportAndReviewer($report->id, $reviewer->id);
        if ($existing !== null) {
            return $existing;
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $ratingOverall = $this->rating($payload['rating_overall'] ?? 5);
        $ratingQuality = $this->rating($payload['rating_quality'] ?? $ratingOverall);
        $ratingCommunication = $this->rating($payload['rating_communication'] ?? $ratingOverall);
        $ratingTimeliness = $this->rating($payload['rating_timeliness'] ?? $ratingOverall);
        $wouldRecommend = (bool) ($payload['would_recommend'] ?? true);
        $issueResolved = (bool) ($payload['issue_resolved'] ?? $report->objectSaved);
        $comment = trim((string) ($payload['comment'] ?? ''));
        $comment = $comment === '' ? null : substr($comment, 0, 1200);

        $signals = [
            'source' => 'completion_report_trust_review',
            'completion_report_id' => $report->id,
            'fulfilment_id' => $report->fulfilmentId,
            'repair_case_id' => $report->repairCaseId,
            'provider_id' => $report->providerId,
            'outcome_status' => $report->outcomeStatus,
            'functional_result' => $report->functionalResult,
            'customer_confirmed' => $report->customerConfirmed,
            'object_saved' => $report->objectSaved,
            'co2_avoided_grams' => $report->co2AvoidedGrams,
            'rating_overall' => $ratingOverall,
            'rating_quality' => $ratingQuality,
            'rating_communication' => $ratingCommunication,
            'rating_timeliness' => $ratingTimeliness,
            'would_recommend' => $wouldRecommend,
            'issue_resolved' => $issueResolved,
            'repair_method' => $report->outcome['repair_method'] ?? null,
            'quality_checks' => $report->outcome['quality_checks'] ?? [],
        ];

        $stmt = $this->pdo->prepare('INSERT INTO provider_trust_reviews (id, completion_report_id, fulfilment_id, repair_case_id, provider_id, reviewer_id, reviewer_role, status, rating_overall, rating_quality, rating_communication, rating_timeliness, would_recommend, issue_resolved, comment, signals_json, created_at, updated_at) VALUES (:id, :completion_report_id, :fulfilment_id, :repair_case_id, :provider_id, :reviewer_id, :reviewer_role, :status, :rating_overall, :rating_quality, :rating_communication, :rating_timeliness, :would_recommend, :issue_resolved, :comment, :signals_json, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'completion_report_id' => $report->id,
            'fulfilment_id' => $report->fulfilmentId,
            'repair_case_id' => $report->repairCaseId,
            'provider_id' => $report->providerId,
            'reviewer_id' => $reviewer->id,
            'reviewer_role' => $reviewer->role,
            'status' => 'published',
            'rating_overall' => $ratingOverall,
            'rating_quality' => $ratingQuality,
            'rating_communication' => $ratingCommunication,
            'rating_timeliness' => $ratingTimeliness,
            'would_recommend' => $wouldRecommend ? 1 : 0,
            'issue_resolved' => $issueResolved ? 1 : 0,
            'comment' => $comment,
            'signals_json' => json_encode($signals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findReview($id) ?? throw new \RuntimeException('Provider trust review creation failed.');
    }

    public function recordSignal(ProviderTrustReview $review, string $eventType, float $scoreDelta): ProviderTrustSignal
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $signal = [
            'source' => 'provider_trust_review',
            'trust_review_id' => $review->id,
            'completion_report_id' => $review->completionReportId,
            'repair_case_id' => $review->repairCaseId,
            'provider_id' => $review->providerId,
            'rating_overall' => $review->ratingOverall,
            'rating_quality' => $review->ratingQuality,
            'rating_communication' => $review->ratingCommunication,
            'rating_timeliness' => $review->ratingTimeliness,
            'would_recommend' => $review->wouldRecommend,
            'issue_resolved' => $review->issueResolved,
            'outcome_status' => $review->signals['outcome_status'] ?? null,
            'object_saved' => $review->signals['object_saved'] ?? null,
        ];

        $stmt = $this->pdo->prepare('INSERT INTO provider_trust_signals (id, provider_id, repair_case_id, completion_report_id, trust_review_id, event_type, signal_json, score_delta, created_at) VALUES (:id, :provider_id, :repair_case_id, :completion_report_id, :trust_review_id, :event_type, :signal_json, :score_delta, :created_at)');
        $stmt->execute([
            'id' => $id,
            'provider_id' => $review->providerId,
            'repair_case_id' => $review->repairCaseId,
            'completion_report_id' => $review->completionReportId,
            'trust_review_id' => $review->id,
            'event_type' => $eventType,
            'signal_json' => json_encode($signal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'score_delta' => $scoreDelta,
            'created_at' => $now,
        ]);

        return $this->findSignal($id) ?? throw new \RuntimeException('Provider trust signal creation failed.');
    }

    public function recalculateQualityScore(string $providerId, ?string $lastReviewId = null): ProviderQualityScore
    {
        $reviews = $this->listReviewsByProvider($providerId);
        $now = gmdate('c');
        $reviewCount = count($reviews);

        if ($reviewCount === 0) {
            $scoreJson = ['summary' => 'Provider has no completed repair trust signal yet.'];
            $stmt = $this->pdo->prepare('INSERT INTO provider_quality_scores (provider_id, review_count, completed_repairs_count, successful_repairs_count, average_rating, quality_score, reliability_score, communication_score, timeliness_score, overall_score, trust_tier, last_review_id, score_json, updated_at) VALUES (:provider_id, 0, 0, 0, 0, 0, 0, 0, 0, 0, :trust_tier, :last_review_id, :score_json, :updated_at) ON CONFLICT(provider_id) DO UPDATE SET updated_at = excluded.updated_at');
            $stmt->execute([
                'provider_id' => $providerId,
                'trust_tier' => 'unrated',
                'last_review_id' => $lastReviewId,
                'score_json' => json_encode($scoreJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
            return $this->findQualityScore($providerId) ?? throw new \RuntimeException('Provider quality score creation failed.');
        }

        $overallRatings = [];
        $qualityRatings = [];
        $communicationRatings = [];
        $timelinessRatings = [];
        $successfulRepairs = 0;
        $objectSaved = 0;
        $recommended = 0;
        $issueResolved = 0;

        foreach ($reviews as $review) {
            $overallRatings[] = $review->ratingOverall;
            $qualityRatings[] = $review->ratingQuality;
            $communicationRatings[] = $review->ratingCommunication;
            $timelinessRatings[] = $review->ratingTimeliness;
            $outcome = (string) ($review->signals['outcome_status'] ?? 'unknown');
            if ($outcome === 'successful') {
                $successfulRepairs++;
            }
            if ((bool) ($review->signals['object_saved'] ?? $review->issueResolved)) {
                $objectSaved++;
            }
            if ($review->wouldRecommend) {
                $recommended++;
            }
            if ($review->issueResolved) {
                $issueResolved++;
            }
        }

        $avgOverall = $this->avg($overallRatings);
        $qualityScore = $this->roundScore(($this->avg($qualityRatings) / 5) * 100);
        $communicationScore = $this->roundScore(($this->avg($communicationRatings) / 5) * 100);
        $timelinessScore = $this->roundScore(($this->avg($timelinessRatings) / 5) * 100);
        $successRate = $successfulRepairs / $reviewCount;
        $objectSavedRate = $objectSaved / $reviewCount;
        $recommendRate = $recommended / $reviewCount;
        $issueResolvedRate = $issueResolved / $reviewCount;
        $reliabilityScore = $this->roundScore((($successRate * 0.45) + ($objectSavedRate * 0.25) + ($recommendRate * 0.15) + ($issueResolvedRate * 0.15)) * 100);
        $overallScore = $this->roundScore(($qualityScore * 0.35) + ($reliabilityScore * 0.35) + ($communicationScore * 0.15) + ($timelinessScore * 0.15));
        $trustTier = $this->tier($overallScore, $reviewCount);
        $completedRepairs = $reviewCount;

        $scoreJson = [
            'formula_version' => 'trust_v1',
            'weights' => [
                'quality' => 0.35,
                'reliability' => 0.35,
                'communication' => 0.15,
                'timeliness' => 0.15,
            ],
            'rates' => [
                'success_rate' => $this->roundScore($successRate * 100),
                'object_saved_rate' => $this->roundScore($objectSavedRate * 100),
                'recommend_rate' => $this->roundScore($recommendRate * 100),
                'issue_resolved_rate' => $this->roundScore($issueResolvedRate * 100),
            ],
            'interpretation' => $this->interpretation($trustTier),
        ];

        $stmt = $this->pdo->prepare('INSERT INTO provider_quality_scores (provider_id, review_count, completed_repairs_count, successful_repairs_count, average_rating, quality_score, reliability_score, communication_score, timeliness_score, overall_score, trust_tier, last_review_id, score_json, updated_at) VALUES (:provider_id, :review_count, :completed_repairs_count, :successful_repairs_count, :average_rating, :quality_score, :reliability_score, :communication_score, :timeliness_score, :overall_score, :trust_tier, :last_review_id, :score_json, :updated_at) ON CONFLICT(provider_id) DO UPDATE SET review_count = excluded.review_count, completed_repairs_count = excluded.completed_repairs_count, successful_repairs_count = excluded.successful_repairs_count, average_rating = excluded.average_rating, quality_score = excluded.quality_score, reliability_score = excluded.reliability_score, communication_score = excluded.communication_score, timeliness_score = excluded.timeliness_score, overall_score = excluded.overall_score, trust_tier = excluded.trust_tier, last_review_id = excluded.last_review_id, score_json = excluded.score_json, updated_at = excluded.updated_at');
        $stmt->execute([
            'provider_id' => $providerId,
            'review_count' => $reviewCount,
            'completed_repairs_count' => $completedRepairs,
            'successful_repairs_count' => $successfulRepairs,
            'average_rating' => round($avgOverall, 2),
            'quality_score' => $qualityScore,
            'reliability_score' => $reliabilityScore,
            'communication_score' => $communicationScore,
            'timeliness_score' => $timelinessScore,
            'overall_score' => $overallScore,
            'trust_tier' => $trustTier,
            'last_review_id' => $lastReviewId,
            'score_json' => json_encode($scoreJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);

        return $this->findQualityScore($providerId) ?? throw new \RuntimeException('Provider quality score update failed.');
    }

    public function findReview(string $id): ?ProviderTrustReview
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_trust_reviews WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderTrustReview::fromRow($row) : null;
    }

    public function listReviewsByCompletionReport(string $completionReportId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_trust_reviews WHERE completion_report_id = :completion_report_id ORDER BY created_at DESC');
        $stmt->execute(['completion_report_id' => $completionReportId]);

        return array_map(static fn(array $row): ProviderTrustReview => ProviderTrustReview::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listReviewsByProvider(string $providerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_trust_reviews WHERE provider_id = :provider_id ORDER BY created_at DESC');
        $stmt->execute(['provider_id' => $providerId]);

        return array_map(static fn(array $row): ProviderTrustReview => ProviderTrustReview::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findQualityScore(string $providerId): ?ProviderQualityScore
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_quality_scores WHERE provider_id = :provider_id');
        $stmt->execute(['provider_id' => $providerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderQualityScore::fromRow($row) : null;
    }

    public function listQualityScores(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM provider_quality_scores ORDER BY overall_score DESC, updated_at DESC');
        return array_map(static fn(array $row): ProviderQualityScore => ProviderQualityScore::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listSignalsByProvider(string $providerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_trust_signals WHERE provider_id = :provider_id ORDER BY created_at DESC');
        $stmt->execute(['provider_id' => $providerId]);

        return array_map(static fn(array $row): ProviderTrustSignal => ProviderTrustSignal::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function findReviewByReportAndReviewer(string $completionReportId, string $reviewerId): ?ProviderTrustReview
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_trust_reviews WHERE completion_report_id = :completion_report_id AND reviewer_id = :reviewer_id LIMIT 1');
        $stmt->execute(['completion_report_id' => $completionReportId, 'reviewer_id' => $reviewerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderTrustReview::fromRow($row) : null;
    }

    private function findSignal(string $id): ?ProviderTrustSignal
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_trust_signals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderTrustSignal::fromRow($row) : null;
    }

    private function rating(mixed $value): int
    {
        return max(1, min(5, (int) $value));
    }

    /** @param list<int> $values */
    private function avg(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        return array_sum($values) / count($values);
    }

    private function roundScore(float $score): float
    {
        return round(max(0, min(100, $score)), 2);
    }

    private function tier(float $score, int $reviewCount): string
    {
        if ($reviewCount === 0) {
            return 'unrated';
        }
        if ($score >= 88 && $reviewCount >= 3) {
            return 'elite';
        }
        if ($score >= 75) {
            return 'trusted';
        }
        if ($score >= 60) {
            return 'qualified';
        }
        if ($score >= 45) {
            return 'emerging';
        }
        return 'watchlist';
    }

    private function interpretation(string $tier): string
    {
        return match ($tier) {
            'elite' => 'Provider has repeated high-quality outcomes and strong reliability signals.',
            'trusted' => 'Provider is trusted for repair fulfilment with strong customer and outcome signals.',
            'qualified' => 'Provider is usable, with more completed repairs needed to strengthen confidence.',
            'emerging' => 'Provider has early trust data but needs more repair outcomes.',
            'watchlist' => 'Provider requires operational review before broader routing.',
            default => 'Provider needs completed repair outcomes before ranking is reliable.',
        };
    }
}
