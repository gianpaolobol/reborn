<?php

declare(strict_types=1);

namespace Reborn\Trust\Domain;

final class ProviderQualityScore
{
    /** @param array<string, mixed> $score */
    public function __construct(
        public readonly string $providerId,
        public readonly int $reviewCount,
        public readonly int $completedRepairsCount,
        public readonly int $successfulRepairsCount,
        public readonly float $averageRating,
        public readonly float $qualityScore,
        public readonly float $reliabilityScore,
        public readonly float $communicationScore,
        public readonly float $timelinessScore,
        public readonly float $overallScore,
        public readonly string $trustTier,
        public readonly ?string $lastReviewId,
        public readonly array $score,
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $score = json_decode((string) ($row['score_json'] ?? '{}'), true);
        if (!is_array($score)) {
            $score = [];
        }

        return new self(
            (string) $row['provider_id'],
            (int) $row['review_count'],
            (int) $row['completed_repairs_count'],
            (int) $row['successful_repairs_count'],
            (float) $row['average_rating'],
            (float) $row['quality_score'],
            (float) $row['reliability_score'],
            (float) $row['communication_score'],
            (float) $row['timeliness_score'],
            (float) $row['overall_score'],
            (string) $row['trust_tier'],
            $row['last_review_id'] !== null ? (string) $row['last_review_id'] : null,
            $score,
            (string) $row['updated_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'review_count' => $this->reviewCount,
            'completed_repairs_count' => $this->completedRepairsCount,
            'successful_repairs_count' => $this->successfulRepairsCount,
            'average_rating' => $this->averageRating,
            'quality_score' => $this->qualityScore,
            'reliability_score' => $this->reliabilityScore,
            'communication_score' => $this->communicationScore,
            'timeliness_score' => $this->timelinessScore,
            'overall_score' => $this->overallScore,
            'trust_tier' => $this->trustTier,
            'last_review_id' => $this->lastReviewId,
            'score_json' => $this->score,
            'updated_at' => $this->updatedAt,
        ];
    }
}
