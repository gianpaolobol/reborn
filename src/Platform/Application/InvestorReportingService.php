<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class InvestorReportingService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $latestSnapshot = $this->latestKpiSnapshot();
        return [
            'summary' => [
                'active_kpis' => $this->count('platform_investor_kpi_definitions', "status = 'active'"),
                'kpi_snapshots' => $this->count('platform_investor_kpi_snapshots'),
                'board_reports' => $this->count('platform_board_reports'),
                'published_reports' => $this->count('platform_board_reports', "status = 'published'"),
                'narrative_sections' => $this->count('platform_demo_narrative_sections', "status = 'active'"),
                'open_readiness_reviews' => $this->count('platform_investor_demo_readiness_reviews', "status IN ('open','needs_work')"),
                'latest_demo_score' => (int) ($latestSnapshot['demo_score'] ?? 0),
                'latest_readiness_status' => (string) ($latestSnapshot['readiness_status'] ?? 'unknown'),
            ],
            'latest_snapshot' => $latestSnapshot,
            'kpi_definitions' => $this->kpiDefinitions('active'),
            'latest_reports' => $this->boardReports('all', 8),
            'narrative_sections' => $this->narrativeSections('active'),
            'readiness_reviews' => $this->readinessReviews('active', 8),
            'scope_note' => 'Step 37 creates local investor/demo and board reporting governance. Metrics are pilot evidence, not audited financials, legal claims or certified ESG reporting.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function kpiDefinitions(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_investor_kpi_definitions';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY category ASC, kpi_key ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function kpiSnapshots(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_investor_kpi_snapshots';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY generated_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeKpiSnapshot'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createKpiSnapshot(array $body, ?string $userId): array
    {
        $now = gmdate('c');
        $id = Uuid::v4();
        $code = 'KPI-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $metrics = $this->calculateMetrics();
        $score = $this->demoScore($metrics);
        $readiness = $this->latestReadinessStatus();
        $status = trim((string) ($body['status'] ?? 'draft')) ?: 'draft';
        if (!in_array($status, ['draft', 'reviewed', 'board_ready', 'archived'], true)) {
            throw new ValidationException(['status' => ['Status must be draft, reviewed, board_ready or archived.']]);
        }

        $stmt = $this->pdo->prepare('INSERT INTO platform_investor_kpi_snapshots (id, snapshot_code, scope, status, objects_saved, repair_cases, providers, maker_profiles, model_assets, repair_bounties, revenue_credit_balance, payout_runs, ai_jobs, geometry_validations, routing_matches, dispatches, accepted_repairs, co2e_avoided_kg, waste_diverted_kg, readiness_status, demo_score, metrics_json, generated_by, generated_at, created_at, updated_at) VALUES (:id, :snapshot_code, :scope, :status, :objects_saved, :repair_cases, :providers, :maker_profiles, :model_assets, :repair_bounties, :revenue_credit_balance, :payout_runs, :ai_jobs, :geometry_validations, :routing_matches, :dispatches, :accepted_repairs, :co2e_avoided_kg, :waste_diverted_kg, :readiness_status, :demo_score, :metrics_json, :generated_by, :generated_at, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'snapshot_code' => $code,
            'scope' => trim((string) ($body['scope'] ?? 'investor_demo')) ?: 'investor_demo',
            'status' => $status,
            'objects_saved' => $metrics['objects_saved'],
            'repair_cases' => $metrics['repair_cases'],
            'providers' => $metrics['providers'],
            'maker_profiles' => $metrics['maker_profiles'],
            'model_assets' => $metrics['model_assets'],
            'repair_bounties' => $metrics['repair_bounties'],
            'revenue_credit_balance' => $metrics['revenue_credit_balance'],
            'payout_runs' => $metrics['payout_runs'],
            'ai_jobs' => $metrics['ai_jobs'],
            'geometry_validations' => $metrics['geometry_validations'],
            'routing_matches' => $metrics['routing_matches'],
            'dispatches' => $metrics['dispatches'],
            'accepted_repairs' => $metrics['accepted_repairs'],
            'co2e_avoided_kg' => $metrics['co2e_avoided_kg'],
            'waste_diverted_kg' => $metrics['waste_diverted_kg'],
            'readiness_status' => $readiness,
            'demo_score' => $score,
            'metrics_json' => json_encode($metrics + ['readiness_status' => $readiness, 'score_inputs' => $this->scoreInputs($metrics)], JSON_THROW_ON_ERROR),
            'generated_by' => $userId,
            'generated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('kpi_snapshot_created', 'investor_kpi_snapshot', $id, sprintf('Investor KPI snapshot %s created.', $code), ['demo_score' => $score, 'readiness_status' => $readiness], $userId);
        return $this->requireKpiSnapshot($id);
    }

    /** @return list<array<string, mixed>> */
    public function narrativeSections(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_demo_narrative_sections';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $row): array {
            $row['sort_order'] = (int) $row['sort_order'];
            $row['proof_points'] = $this->decodeJson($row['proof_points_json'] ?? '[]');
            unset($row['proof_points_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function boardReports(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*, s.snapshot_code, s.demo_score, s.readiness_status FROM platform_board_reports r LEFT JOIN platform_investor_kpi_snapshots s ON s.id = r.kpi_snapshot_id';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE r.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeBoardReport'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createBoardReport(array $body, ?string $userId): array
    {
        $snapshotId = trim((string) ($body['kpi_snapshot_id'] ?? '')) ?: null;
        $snapshot = $snapshotId ? $this->requireKpiSnapshot($snapshotId) : ($this->latestKpiSnapshot() ?? $this->createKpiSnapshot(['status' => 'draft'], $userId));
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'BOARD-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $title = trim((string) ($body['title'] ?? 'Re-born Investor Demo Board Report')) ?: 'Re-born Investor Demo Board Report';
        $period = trim((string) ($body['period_label'] ?? gmdate('Y-m'))) ?: gmdate('Y-m');
        $summary = $this->executiveSummary($snapshot);
        $risks = $body['risks'] ?? [
            'AI providers remain sandboxed until live integrations are approved.',
            'Payments, payouts, refunds and KYC/KYB remain mock/local governance.',
            'Sustainability impact remains pilot estimate and cannot be used as a certified public claim.',
        ];
        $asks = $body['asks'] ?? [
            'Approve pilot cohort narrative and demo path.',
            'Prioritize live AI/provider/payment integrations after full smoke suite passes.',
            'Collect partner/customer evidence for beta entry decision.',
        ];

        $stmt = $this->pdo->prepare('INSERT INTO platform_board_reports (id, report_code, report_type, status, title, period_label, kpi_snapshot_id, executive_summary, risks_json, asks_json, created_by, created_at, updated_at, published_at) VALUES (:id, :report_code, :report_type, :status, :title, :period_label, :kpi_snapshot_id, :executive_summary, :risks_json, :asks_json, :created_by, :created_at, :updated_at, :published_at)');
        $stmt->execute([
            'id' => $id,
            'report_code' => $code,
            'report_type' => trim((string) ($body['report_type'] ?? 'investor_demo')) ?: 'investor_demo',
            'status' => 'draft',
            'title' => $title,
            'period_label' => $period,
            'kpi_snapshot_id' => $snapshot['id'],
            'executive_summary' => $summary,
            'risks_json' => json_encode($risks, JSON_THROW_ON_ERROR),
            'asks_json' => json_encode($asks, JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => null,
        ]);

        $this->createReportSections($id, $snapshot);
        $this->createReportEvidence($id, $snapshot, $userId);
        $this->audit('board_report_created', 'board_report', $id, sprintf('Board report %s created.', $code), ['snapshot_code' => $snapshot['snapshot_code']], $userId);
        return $this->requireBoardReport($id);
    }

    /** @return array<string, mixed> */
    public function publishBoardReport(string $id, array $body, ?string $userId): array
    {
        $report = $this->requireBoardReport($id);
        $status = trim((string) ($body['status'] ?? 'published')) ?: 'published';
        if (!in_array($status, ['reviewed', 'published', 'archived'], true)) {
            throw new ValidationException(['status' => ['Status must be reviewed, published or archived.']]);
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_board_reports SET status = :status, updated_at = :updated_at, published_at = :published_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'updated_at' => $now,
            'published_at' => $status === 'published' ? $now : ($report['published_at'] ?? null),
            'id' => $id,
        ]);
        $this->audit('board_report_status_updated', 'board_report', $id, sprintf('Board report %s moved to %s.', $report['report_code'], $status), ['status' => $status], $userId);
        return $this->requireBoardReport($id);
    }

    /** @return list<array<string, mixed>> */
    public function boardReportSections(string $reportId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_board_report_sections WHERE board_report_id = :id ORDER BY sort_order ASC');
        $stmt->execute(['id' => $reportId]);
        return array_map(function (array $row): array {
            $row['sort_order'] = (int) $row['sort_order'];
            $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '[]');
            unset($row['evidence_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function boardReportEvidence(?string $reportId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_board_report_evidence';
        $params = [];
        if ($reportId) {
            $sql .= ' WHERE board_report_id = :report_id';
            $params['report_id'] = $reportId;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function readinessReviews(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_investor_demo_readiness_reviews';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE status IN ('open','needs_work')";
            } else {
                $sql .= ' WHERE status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeReadinessReview'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function evaluateDemoReadiness(array $body, ?string $userId): array
    {
        $snapshot = $this->latestKpiSnapshot() ?? $this->createKpiSnapshot(['status' => 'draft'], $userId);
        $blocking = [];
        $next = [
            'Run the full smoke suite in C:\\REBORN\\REBORN before any investor demo.',
            'Prepare a scripted 5-minute demo path from intake to impact board report.',
            'Qualify all metrics as pilot/local evidence until real beta data exists.',
        ];
        if (($snapshot['ai_jobs'] ?? 0) < 1) {
            $blocking[] = 'AI provider sandbox has no orchestration jobs yet.';
        }
        if (($snapshot['accepted_repairs'] ?? 0) < 1) {
            $blocking[] = 'No accepted repair/customer acceptance evidence yet.';
        }
        if (($snapshot['objects_saved'] ?? 0) < 1) {
            $blocking[] = 'No calculated object-saved impact evidence yet.';
        }
        if (($snapshot['readiness_status'] ?? 'unknown') === 'not_ready') {
            $blocking[] = 'Platform readiness is not_ready.';
        }

        $score = max(0, min(100, (int) ($snapshot['demo_score'] ?? 0) - count($blocking) * 6));
        $level = $score >= 80 && $blocking === [] ? 'investor_demo_ready' : ($score >= 60 ? 'demo_ready_with_caveats' : 'needs_work_before_demo');
        $status = $level === 'needs_work_before_demo' ? 'needs_work' : 'open';
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'INV-READY-' . gmdate('Ymd-His') . '-' . strtoupper(substr(str_replace('-', '', $id), 0, 6));
        $notes = trim((string) ($body['notes'] ?? 'Automated Step 37 investor demo readiness evaluation.')) ?: 'Automated Step 37 investor demo readiness evaluation.';

        $stmt = $this->pdo->prepare('INSERT INTO platform_investor_demo_readiness_reviews (id, review_code, status, readiness_level, score, blocking_issues_json, recommended_next_steps_json, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :review_code, :status, :readiness_level, :score, :blocking_issues_json, :recommended_next_steps_json, :notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'review_code' => $code,
            'status' => $status,
            'readiness_level' => $level,
            'score' => $score,
            'blocking_issues_json' => json_encode($blocking, JSON_THROW_ON_ERROR),
            'recommended_next_steps_json' => json_encode($next, JSON_THROW_ON_ERROR),
            'notes' => $notes,
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        $this->audit('investor_demo_readiness_evaluated', 'investor_demo_readiness_review', $id, sprintf('Investor demo readiness %s evaluated at %s.', $code, $level), ['score' => $score, 'blocking_issues' => $blocking], $userId);
        return $this->requireReadinessReview($id);
    }

    /** @return array<string, mixed> */
    public function reviewDemoReadiness(string $id, array $body, ?string $userId): array
    {
        $review = $this->requireReadinessReview($id);
        $decision = trim((string) ($body['decision'] ?? 'reviewed_with_caveats')) ?: 'reviewed_with_caveats';
        if (!in_array($decision, ['approved_for_demo', 'reviewed_with_caveats', 'needs_work', 'archived'], true)) {
            throw new ValidationException(['decision' => ['Decision must be approved_for_demo, reviewed_with_caveats, needs_work or archived.']]);
        }
        $status = $decision === 'approved_for_demo' ? 'approved' : ($decision === 'needs_work' ? 'needs_work' : ($decision === 'archived' ? 'archived' : 'reviewed'));
        $notes = trim((string) ($body['notes'] ?? $review['notes'])) ?: $review['notes'];
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_investor_demo_readiness_reviews SET status = :status, notes = :notes, reviewed_by = :reviewed_by, updated_at = :updated_at, reviewed_at = :reviewed_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'notes' => $notes,
            'reviewed_by' => $userId,
            'updated_at' => $now,
            'reviewed_at' => $now,
            'id' => $id,
        ]);
        $this->audit('investor_demo_readiness_reviewed', 'investor_demo_readiness_review', $id, sprintf('Investor demo readiness %s reviewed as %s.', $review['review_code'], $status), ['decision' => $decision], $userId);
        return $this->requireReadinessReview($id);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_investor_reporting_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function calculateMetrics(): array
    {
        $creditBalance = (int) round($this->sum('platform_credit_accounts', 'balance_credits'));
        return [
            'objects_saved' => $this->count('platform_repair_impact_records', "status IN ('calculated','accepted','published_internal') AND repair_score >= 50"),
            'repair_cases' => $this->count('repair_cases'),
            'providers' => $this->count('providers') + $this->count('platform_partner_accounts', "partner_type IN ('provider','enterprise','manufacturer')"),
            'maker_profiles' => $this->count('platform_maker_profiles'),
            'model_assets' => $this->count('platform_repair_model_assets'),
            'repair_bounties' => $this->count('platform_repair_bounties'),
            'revenue_credit_balance' => $creditBalance,
            'payout_runs' => $this->count('platform_payout_runs'),
            'ai_jobs' => $this->count('platform_ai_orchestration_jobs'),
            'geometry_validations' => $this->count('platform_geometry_validation_runs'),
            'routing_matches' => $this->count('platform_provider_routing_matches'),
            'dispatches' => $this->count('platform_fulfilment_dispatches'),
            'accepted_repairs' => $this->count('platform_customer_acceptance_records', "acceptance_decision = 'accepted' OR status IN ('accepted','completed')"),
            'co2e_avoided_kg' => round($this->sum('platform_repair_impact_records', 'co2e_avoided_kg', "status IN ('calculated','accepted','published_internal')"), 3),
            'waste_diverted_kg' => round($this->sum('platform_repair_impact_records', 'waste_diverted_kg', "status IN ('calculated','accepted','published_internal')"), 3),
            'open_alerts' => $this->count('platform_alerts', "status IN ('open','acknowledged')"),
            'open_incidents' => $this->count('platform_incidents', "status NOT IN ('resolved','closed')"),
            'open_privacy_requests' => $this->count('platform_data_subject_requests', "status IN ('open','in_progress')"),
            'open_reviews' => $this->count('platform_investor_demo_readiness_reviews', "status IN ('open','needs_work')"),
        ];
    }

    /** @param array<string, mixed> $metrics */
    private function demoScore(array $metrics): int
    {
        $score = 35;
        $score += min(12, (int) $metrics['repair_cases'] * 2);
        $score += min(10, (int) $metrics['providers'] * 2);
        $score += min(8, (int) $metrics['maker_profiles'] * 2 + (int) $metrics['model_assets']);
        $score += min(8, (int) $metrics['ai_jobs'] * 2);
        $score += min(7, (int) $metrics['geometry_validations'] * 2 + (int) $metrics['routing_matches']);
        $score += min(8, (int) $metrics['accepted_repairs'] * 3 + (int) $metrics['objects_saved'] * 2);
        $score += min(6, (int) floor((float) $metrics['co2e_avoided_kg']));
        $score -= min(12, ((int) $metrics['open_alerts'] + (int) $metrics['open_incidents']) * 2);
        $score -= min(8, (int) $metrics['open_privacy_requests'] * 2);
        return max(0, min(100, $score));
    }

    /** @param array<string, mixed> $metrics @return array<string, mixed> */
    private function scoreInputs(array $metrics): array
    {
        return [
            'positive' => ['repair_cases', 'providers', 'maker_profiles', 'model_assets', 'ai_jobs', 'geometry_validations', 'routing_matches', 'accepted_repairs', 'objects_saved', 'co2e_avoided_kg'],
            'negative' => ['open_alerts', 'open_incidents', 'open_privacy_requests'],
            'metrics' => $metrics,
        ];
    }

    private function latestReadinessStatus(): string
    {
        if (!$this->tableExists('platform_readiness_snapshots')) {
            return 'unknown';
        }
        $stmt = $this->pdo->query('SELECT status FROM platform_readiness_snapshots ORDER BY created_at DESC LIMIT 1');
        $status = $stmt ? $stmt->fetchColumn() : false;
        return $status ? (string) $status : 'unknown';
    }

    /** @return array<string, mixed>|null */
    private function latestKpiSnapshot(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM platform_investor_kpi_snapshots ORDER BY generated_at DESC LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ? $this->normalizeKpiSnapshot($row) : null;
    }

    /** @return array<string, mixed> */
    private function requireKpiSnapshot(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_investor_kpi_snapshots WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['KPI snapshot was not found.']]);
        }
        return $this->normalizeKpiSnapshot($row);
    }

    /** @return array<string, mixed> */
    private function requireBoardReport(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, s.snapshot_code, s.demo_score, s.readiness_status FROM platform_board_reports r LEFT JOIN platform_investor_kpi_snapshots s ON s.id = r.kpi_snapshot_id WHERE r.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['Board report was not found.']]);
        }
        $report = $this->normalizeBoardReport($row);
        $report['sections'] = $this->boardReportSections($id);
        $report['evidence'] = $this->boardReportEvidence($id, 50);
        return $report;
    }

    /** @return array<string, mixed> */
    private function requireReadinessReview(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_investor_demo_readiness_reviews WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['Investor readiness review was not found.']]);
        }
        return $this->normalizeReadinessReview($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeKpiSnapshot(array $row): array
    {
        foreach (['objects_saved','repair_cases','providers','maker_profiles','model_assets','repair_bounties','revenue_credit_balance','payout_runs','ai_jobs','geometry_validations','routing_matches','dispatches','accepted_repairs','demo_score'] as $field) {
            $row[$field] = (int) ($row[$field] ?? 0);
        }
        foreach (['co2e_avoided_kg','waste_diverted_kg'] as $field) {
            $row[$field] = (float) ($row[$field] ?? 0);
        }
        $row['metrics'] = $this->decodeJson($row['metrics_json'] ?? '{}');
        unset($row['metrics_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeBoardReport(array $row): array
    {
        $row['demo_score'] = isset($row['demo_score']) ? (int) $row['demo_score'] : null;
        $row['risks'] = $this->decodeJson($row['risks_json'] ?? '[]');
        $row['asks'] = $this->decodeJson($row['asks_json'] ?? '[]');
        unset($row['risks_json'], $row['asks_json']);
        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeReadinessReview(array $row): array
    {
        $row['score'] = (int) ($row['score'] ?? 0);
        $row['blocking_issues'] = $this->decodeJson($row['blocking_issues_json'] ?? '[]');
        $row['recommended_next_steps'] = $this->decodeJson($row['recommended_next_steps_json'] ?? '[]');
        unset($row['blocking_issues_json'], $row['recommended_next_steps_json']);
        return $row;
    }

    /** @param array<string, mixed> $snapshot */
    private function executiveSummary(array $snapshot): string
    {
        return sprintf(
            'Re-born is demo-ready as a local Repair Intelligence Platform with %d repair cases, %d providers/partners, %d maker assets, %d AI sandbox jobs, %d routing matches and %d objects saved/pilot impacts. The report explicitly separates proven local workflow evidence from mock integrations and future production requirements.',
            (int) ($snapshot['repair_cases'] ?? 0),
            (int) ($snapshot['providers'] ?? 0),
            (int) ($snapshot['model_assets'] ?? 0),
            (int) ($snapshot['ai_jobs'] ?? 0),
            (int) ($snapshot['routing_matches'] ?? 0),
            (int) ($snapshot['objects_saved'] ?? 0)
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function createReportSections(string $reportId, array $snapshot): void
    {
        $now = gmdate('c');
        $sections = $this->narrativeSections('active');
        if ($sections === []) {
            $sections = [[
                'section_key' => 'summary',
                'title' => 'Summary',
                'sort_order' => 10,
                'narrative' => 'Re-born local demo report.',
                'proof_points' => [],
            ]];
        }
        foreach ($sections as $section) {
            $id = Uuid::v4();
            $content = $section['narrative'];
            if (($section['section_key'] ?? '') === 'readiness') {
                $content .= sprintf(' Latest demo score: %d/100; readiness status: %s.', (int) ($snapshot['demo_score'] ?? 0), (string) ($snapshot['readiness_status'] ?? 'unknown'));
            }
            $stmt = $this->pdo->prepare('INSERT INTO platform_board_report_sections (id, board_report_id, section_key, title, content, sort_order, evidence_json, created_at, updated_at) VALUES (:id, :board_report_id, :section_key, :title, :content, :sort_order, :evidence_json, :created_at, :updated_at)');
            $stmt->execute([
                'id' => $id,
                'board_report_id' => $reportId,
                'section_key' => $section['section_key'],
                'title' => $section['title'],
                'content' => $content,
                'sort_order' => (int) ($section['sort_order'] ?? 0),
                'evidence_json' => json_encode($section['proof_points'] ?? [], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function createReportEvidence(string $reportId, array $snapshot, ?string $userId): void
    {
        $items = [
            ['kpi_snapshot', $snapshot['id'] ?? null, 'Investor KPI snapshot', 'Aggregated local KPI evidence generated from platform tables.'],
            ['readiness', null, 'Readiness status', 'Latest readiness status: ' . (string) ($snapshot['readiness_status'] ?? 'unknown') . '.'],
            ['sustainability', null, 'Impact caveat', 'Sustainability metrics are pilot estimates and are not public certified claims.'],
        ];
        foreach ($items as $item) {
            $id = Uuid::v4();
            $stmt = $this->pdo->prepare('INSERT INTO platform_board_report_evidence (id, board_report_id, evidence_type, source_entity_type, source_entity_id, title, summary, confidence_level, metadata_json, created_by, created_at) VALUES (:id, :board_report_id, :evidence_type, :source_entity_type, :source_entity_id, :title, :summary, :confidence_level, :metadata_json, :created_by, :created_at)');
            $stmt->execute([
                'id' => $id,
                'board_report_id' => $reportId,
                'evidence_type' => 'local_pilot_evidence',
                'source_entity_type' => $item[0],
                'source_entity_id' => $item[1],
                'title' => $item[2],
                'summary' => $item[3],
                'confidence_level' => 'pilot_evidence',
                'metadata_json' => json_encode(['step' => 37, 'public_claim' => false], JSON_THROW_ON_ERROR),
                'created_by' => $userId,
                'created_at' => gmdate('c'),
            ]);
        }
    }

    private function count(string $table, ?string $where = null): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        $result = $this->pdo->query($sql);
        return $result ? (int) $result->fetchColumn() : 0;
    }

    private function sum(string $table, string $column, ?string $where = null): float
    {
        if (!$this->tableExists($table)) {
            return 0.0;
        }
        $sql = 'SELECT COALESCE(SUM(' . $column . '), 0) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        $result = $this->pdo->query($sql);
        return $result ? (float) $result->fetchColumn() : 0.0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return mixed */
    private function decodeJson(?string $json): mixed
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata = [], ?string $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_investor_reporting_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }
}
