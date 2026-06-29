<?php

declare(strict_types=1);

namespace Reborn\Governance\Infrastructure;

use PDO;
use Reborn\Governance\Domain\MarketplaceGovernanceRepository;
use Reborn\Governance\Domain\ProviderGovernanceAction;
use Reborn\Governance\Domain\ProviderRankingSnapshot;
use Reborn\Identity\Domain\User;
use Reborn\Shared\Support\Uuid;

final class SqliteMarketplaceGovernanceRepository implements MarketplaceGovernanceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $payload */
    public function recordProviderAction(string $providerId, User $actor, array $payload): ProviderGovernanceAction
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $actionType = $this->actionType($payload['action_type'] ?? 'watchlist');
        $severity = $this->severity($payload['severity'] ?? 'medium');
        $reason = trim((string) ($payload['reason'] ?? 'Marketplace governance review.'));
        $reason = $reason === '' ? 'Marketplace governance review.' : substr($reason, 0, 500);
        $notes = trim((string) ($payload['notes'] ?? ''));
        $notes = $notes === '' ? null : substr($notes, 0, 1600);
        $scoreAdjustment = $this->scoreAdjustment($payload['score_adjustment'] ?? $this->defaultAdjustment($actionType));
        $expiresAt = isset($payload['expires_at']) && trim((string) $payload['expires_at']) !== '' ? (string) $payload['expires_at'] : null;

        $stmt = $this->pdo->prepare('INSERT INTO provider_governance_actions (id, provider_id, action_type, severity, status, reason, notes, score_adjustment, expires_at, created_by, created_at, resolved_at) VALUES (:id, :provider_id, :action_type, :severity, :status, :reason, :notes, :score_adjustment, :expires_at, :created_by, :created_at, :resolved_at)');
        $stmt->execute([
            'id' => $id,
            'provider_id' => $providerId,
            'action_type' => $actionType,
            'severity' => $severity,
            'status' => 'active',
            'reason' => $reason,
            'notes' => $notes,
            'score_adjustment' => $scoreAdjustment,
            'expires_at' => $expiresAt,
            'created_by' => $actor->id,
            'created_at' => $now,
            'resolved_at' => null,
        ]);

        return $this->findAction($id) ?? throw new \RuntimeException('Provider governance action creation failed.');
    }

    public function listProviderActions(string $providerId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM provider_governance_actions WHERE provider_id = :provider_id';
        if ($activeOnly) {
            $sql .= " AND status = 'active' AND (expires_at IS NULL OR expires_at > :now)";
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('provider_id', $providerId);
        if ($activeOnly) {
            $stmt->bindValue('now', gmdate('c'));
        }
        $stmt->execute();

        return array_map(static fn(array $row): ProviderGovernanceAction => ProviderGovernanceAction::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listActions(?string $status = null): array
    {
        if ($status !== null && $status !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM provider_governance_actions WHERE status = :status ORDER BY created_at DESC');
            $stmt->execute(['status' => $status]);
        } else {
            $stmt = $this->pdo->query('SELECT * FROM provider_governance_actions ORDER BY created_at DESC');
        }

        return array_map(static fn(array $row): ProviderGovernanceAction => ProviderGovernanceAction::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param list<array<string, mixed>> $ranking @param array<string, mixed> $policy */
    public function createRankingSnapshot(User $actor, array $ranking, array $policy, string $formulaVersion): ProviderRankingSnapshot
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO provider_ranking_snapshots (id, status, ranking_formula_version, provider_count, ranking_json, policy_json, created_by, created_at) VALUES (:id, :status, :ranking_formula_version, :provider_count, :ranking_json, :policy_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'status' => 'published',
            'ranking_formula_version' => $formulaVersion,
            'provider_count' => count($ranking),
            'ranking_json' => json_encode($ranking, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'policy_json' => json_encode($policy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $actor->id,
            'created_at' => $now,
        ]);

        return $this->findSnapshot($id) ?? throw new \RuntimeException('Provider ranking snapshot creation failed.');
    }

    public function latestRankingSnapshot(): ?ProviderRankingSnapshot
    {
        $stmt = $this->pdo->query('SELECT * FROM provider_ranking_snapshots ORDER BY rowid DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderRankingSnapshot::fromRow($row) : null;
    }

    public function currentProviderRankings(): array
    {
        $snapshot = $this->latestRankingSnapshot();
        return $snapshot?->ranking ?? [];
    }

    public function audit(User $actor, string $action, string $subjectType, string $subjectId, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO marketplace_governance_audit (id, actor_id, action, subject_type, subject_id, payload_json, created_at) VALUES (:id, :actor_id, :action, :subject_type, :subject_id, :payload_json, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => gmdate('c'),
        ]);
    }

    public function summary(): array
    {
        $activeActions = (int) $this->pdo->query("SELECT COUNT(*) FROM provider_governance_actions WHERE status = 'active' AND (expires_at IS NULL OR expires_at > datetime('now'))")->fetchColumn();
        $snapshots = (int) $this->pdo->query('SELECT COUNT(*) FROM provider_ranking_snapshots')->fetchColumn();
        $latest = $this->latestRankingSnapshot();
        $rankings = $latest?->ranking ?? [];
        $eligible = 0;
        $watchlist = 0;
        $suppressed = 0;
        foreach ($rankings as $ranking) {
            $status = (string) ($ranking['routing_status'] ?? 'eligible');
            if ($status === 'suppressed') {
                $suppressed++;
            } elseif ($status === 'watchlist') {
                $watchlist++;
            } else {
                $eligible++;
            }
        }

        return [
            'active_governance_actions' => $activeActions,
            'ranking_snapshots' => $snapshots,
            'latest_snapshot_id' => $latest?->id,
            'latest_snapshot_at' => $latest?->createdAt,
            'provider_count' => $latest?->providerCount ?? 0,
            'routing_status_counts' => [
                'eligible' => $eligible,
                'watchlist' => $watchlist,
                'suppressed' => $suppressed,
            ],
        ];
    }


    private function findSnapshot(string $id): ?ProviderRankingSnapshot
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_ranking_snapshots WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderRankingSnapshot::fromRow($row) : null;
    }

    private function findAction(string $id): ?ProviderGovernanceAction
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_governance_actions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderGovernanceAction::fromRow($row) : null;
    }

    private function actionType(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['watchlist', 'suppress', 'manual_boost', 'manual_penalty', 'quality_review', 'policy_note'], true) ? $value : 'watchlist';
    }

    private function severity(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['low', 'medium', 'high', 'critical'], true) ? $value : 'medium';
    }

    private function scoreAdjustment(mixed $value): float
    {
        return round(max(-100, min(100, (float) $value)), 2);
    }

    private function defaultAdjustment(string $actionType): float
    {
        return match ($actionType) {
            'suppress' => -100.0,
            'manual_boost' => 8.0,
            'manual_penalty' => -12.0,
            'quality_review' => -6.0,
            'policy_note' => 0.0,
            default => -10.0,
        };
    }
}
