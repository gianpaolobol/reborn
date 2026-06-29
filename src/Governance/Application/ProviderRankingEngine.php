<?php

declare(strict_types=1);

namespace Reborn\Governance\Application;

use PDO;
use Reborn\Governance\Domain\MarketplaceGovernanceRepository;

final class ProviderRankingEngine
{
    public const FORMULA_VERSION = 'provider_ranking_governance_v1';

    public function __construct(
        private readonly PDO $pdo,
        private readonly MarketplaceGovernanceRepository $governanceRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function rank(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, city, country, capabilities, rating, average_lead_time_days FROM providers ORDER BY name ASC');
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rankings = [];

        foreach ($providers as $provider) {
            $providerId = (string) $provider['id'];
            $score = $this->qualityScore($providerId);
            $actions = $this->governanceRepository->listProviderActions($providerId, true);
            $actionArrays = array_map(static fn($action): array => $action->toArray(), $actions);
            $baseScore = $this->baseScore($provider, $score);
            $adjustment = array_sum(array_map(static fn($action): float => (float) $action->scoreAdjustment, $actions));
            $finalScore = $this->clamp($baseScore + $adjustment);
            $routingStatus = $this->routingStatus($finalScore, $actions);
            $trustTier = (string) ($score['trust_tier'] ?? $this->seedTier($baseScore));

            $rankings[] = [
                'provider_id' => $providerId,
                'provider_name' => (string) $provider['name'],
                'city' => (string) $provider['city'],
                'country' => (string) $provider['country'],
                'capabilities' => json_decode((string) ($provider['capabilities'] ?? '[]'), true) ?: [],
                'seed_rating' => (float) $provider['rating'],
                'average_lead_time_days' => (int) $provider['average_lead_time_days'],
                'base_score' => $baseScore,
                'governance_adjustment' => round($adjustment, 2),
                'final_score' => $finalScore,
                'rank' => 0,
                'routing_status' => $routingStatus,
                'trust_tier' => $trustTier,
                'review_count' => (int) ($score['review_count'] ?? 0),
                'quality_score' => (float) ($score['quality_score'] ?? 0),
                'reliability_score' => (float) ($score['reliability_score'] ?? 0),
                'active_governance_actions' => $actionArrays,
                'explanation' => $this->explanation($routingStatus, $baseScore, $adjustment, (int) ($score['review_count'] ?? 0)),
            ];
        }

        usort($rankings, static function (array $a, array $b): int {
            if ($a['routing_status'] === 'suppressed' && $b['routing_status'] !== 'suppressed') {
                return 1;
            }
            if ($b['routing_status'] === 'suppressed' && $a['routing_status'] !== 'suppressed') {
                return -1;
            }
            return ($b['final_score'] <=> $a['final_score']) ?: strcmp((string) $a['provider_name'], (string) $b['provider_name']);
        });

        foreach ($rankings as $index => $ranking) {
            $rankings[$index]['rank'] = $index + 1;
        }

        return $rankings;
    }

    /** @param array<string, mixed> $provider @param array<string, mixed>|null $score */
    private function baseScore(array $provider, ?array $score): float
    {
        if ($score !== null && (int) ($score['review_count'] ?? 0) > 0) {
            $quality = (float) ($score['quality_score'] ?? 0);
            $reliability = (float) ($score['reliability_score'] ?? 0);
            $communication = (float) ($score['communication_score'] ?? 0);
            $timeliness = (float) ($score['timeliness_score'] ?? 0);
            $seed = ((float) $provider['rating'] / 5) * 100;
            return $this->clamp(($quality * 0.50) + ($reliability * 0.25) + ($communication * 0.10) + ($timeliness * 0.10) + ($seed * 0.05));
        }

        $seedRating = ((float) $provider['rating'] / 5) * 100;
        $leadTime = max(0, 100 - (((int) $provider['average_lead_time_days']) * 6));
        return $this->clamp(($seedRating * 0.75) + ($leadTime * 0.25));
    }

    /** @return array<string, mixed>|null */
    private function qualityScore(string $providerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_quality_scores WHERE provider_id = :provider_id');
        $stmt->execute(['provider_id' => $providerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row['review_count'] = (int) $row['review_count'];
        $row['quality_score'] = (float) $row['quality_score'];
        $row['reliability_score'] = (float) $row['reliability_score'];
        $row['communication_score'] = (float) $row['communication_score'];
        $row['timeliness_score'] = (float) $row['timeliness_score'];
        return $row;
    }

    /** @param list<object> $actions */
    private function routingStatus(float $finalScore, array $actions): string
    {
        foreach ($actions as $action) {
            if ($action->actionType === 'suppress') {
                return 'suppressed';
            }
        }
        foreach ($actions as $action) {
            if (in_array($action->actionType, ['watchlist', 'quality_review'], true)) {
                return 'watchlist';
            }
        }
        if ($finalScore < 35) {
            return 'suppressed';
        }
        if ($finalScore < 60) {
            return 'watchlist';
        }
        return 'eligible';
    }

    private function seedTier(float $baseScore): string
    {
        if ($baseScore >= 80) {
            return 'seed_trusted';
        }
        if ($baseScore >= 60) {
            return 'seed_qualified';
        }
        return 'unrated';
    }

    private function explanation(string $routingStatus, float $baseScore, float $adjustment, int $reviewCount): string
    {
        $source = $reviewCount > 0 ? 'verified repair outcomes' : 'seed provider profile until repair outcomes exist';
        $modifier = $adjustment === 0.0 ? 'no manual governance adjustment' : 'manual governance adjustment applied';
        return sprintf('Ranking uses %s, base score %.2f, %s. Routing status: %s.', $source, $baseScore, $modifier, $routingStatus);
    }

    private function clamp(float $score): float
    {
        return round(max(0, min(100, $score)), 2);
    }
}
