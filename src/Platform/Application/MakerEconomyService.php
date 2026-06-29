<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class MakerEconomyService
{
    /** @var list<string> */
    private array $profileStatuses = ['onboarding', 'active', 'paused', 'suspended'];

    /** @var list<string> */
    private array $modelStatuses = ['submitted', 'in_review', 'approved', 'rejected', 'retired'];

    /** @var list<string> */
    private array $bountyStatuses = ['draft', 'open', 'in_review', 'awarded', 'closed', 'cancelled'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'maker_economy_version' => 'maker_economy_model_licensing_repair_bounties_v1_step29',
            'generated_at' => gmdate('c'),
            'summary' => $this->summary(),
            'maker_profiles' => $this->makerProfiles('all', 20),
            'model_assets' => $this->modelAssets('all', 20),
            'model_licenses' => $this->modelLicenses('all'),
            'recent_downloads' => $this->modelDownloads(20),
            'recent_royalty_events' => $this->royaltyEvents(20),
            'repair_bounties' => $this->repairBounties('active', 20),
            'bounty_submissions' => $this->bountySubmissions(null, 20),
            'operator_actions' => $this->operatorActions(),
            'important_notes' => [
                'Step 29 governs maker participation, model licensing and repair bounties; it does not publish a public STL marketplace.',
                'Model downloads and royalty events are local pilot records only; no real file delivery, cash royalty or tax workflow is enabled.',
                'Repair bounties are tied to the Repair Journey and object restoration outcomes, not speculative model uploads.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'maker_profiles_total' => $this->count('platform_maker_profiles'),
            'maker_profiles_active' => $this->count('platform_maker_profiles', "status = 'active'"),
            'model_assets_total' => $this->count('platform_model_assets'),
            'model_assets_approved' => $this->count('platform_model_assets', "status = 'approved'"),
            'model_assets_in_review' => $this->count('platform_model_assets', "status IN ('submitted', 'in_review')"),
            'active_model_licenses' => $this->count('platform_model_licenses', "status = 'active'"),
            'model_downloads_recorded' => $this->count('platform_model_downloads'),
            'royalty_events_posted' => $this->count('platform_model_royalty_events', "status = 'posted'"),
            'royalty_credits_awarded' => $this->sum('platform_model_royalty_events', 'credits_awarded', "status = 'posted'"),
            'repair_bounties_open' => $this->count('platform_repair_bounties', "status = 'open'"),
            'bounty_submissions_pending' => $this->count('platform_bounty_submissions', "status IN ('submitted', 'in_review')"),
            'bounty_credits_awarded' => $this->sum('platform_bounty_submissions', 'awarded_credits', "status = 'accepted'"),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function makerProfiles(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT p.*, ca.balance_credits, ca.lifetime_earned_credits, pa.status AS payout_status FROM platform_maker_profiles p LEFT JOIN platform_credit_accounts ca ON ca.id = p.credit_account_id LEFT JOIN platform_payout_accounts pa ON pa.id = p.payout_account_id';
        $params = [];
        if (in_array($status, $this->profileStatuses, true)) {
            $sql .= ' WHERE p.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE p.status WHEN \'active\' THEN 1 WHEN \'onboarding\' THEN 2 ELSE 3 END, p.updated_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeMakerProfile'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createMakerProfile(array $body, ?string $userId): array
    {
        $makerRef = trim((string) ($body['maker_ref'] ?? ''));
        if ($makerRef === '') {
            throw new ValidationException(['maker_ref' => ['maker_ref is required.']]);
        }

        $displayName = trim((string) ($body['display_name'] ?? $makerRef));
        $status = strtolower(trim((string) ($body['status'] ?? 'onboarding')));
        if (!in_array($status, $this->profileStatuses, true)) {
            throw new ValidationException(['status' => ['status must be onboarding, active, paused or suspended.']]);
        }

        $tags = $this->stringList($body['specialty_tags'] ?? ['repair_parts']);
        $now = gmdate('c');
        $creditAccountId = $this->ensureCreditAccount($makerRef, $displayName, $userId);
        $id = Uuid::v4();

        $stmt = $this->pdo->prepare('INSERT INTO platform_maker_profiles (id, maker_ref, display_name, status, trust_tier, specialty_tags_json, credit_account_id, payout_account_id, notes, created_by, created_at, updated_at) VALUES (:id, :maker_ref, :display_name, :status, :trust_tier, :tags, :credit_account_id, :payout_account_id, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'maker_ref' => $makerRef,
            'display_name' => $displayName,
            'status' => $status,
            'trust_tier' => strtolower(trim((string) ($body['trust_tier'] ?? 'new'))) ?: 'new',
            'tags' => json_encode($tags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'credit_account_id' => $creditAccountId,
            'payout_account_id' => trim((string) ($body['payout_account_id'] ?? '')) ?: null,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('maker_profile_created', 'maker_profile', $id, sprintf('Maker profile created for %s.', $makerRef), ['status' => $status], $userId);
        return $this->requireMakerProfile($id);
    }

    /** @return array<string, mixed> */
    public function updateMakerProfileStatus(string $id, array $body, ?string $userId): array
    {
        $profile = $this->requireMakerProfile($id);
        $status = strtolower(trim((string) ($body['status'] ?? '')));
        if (!in_array($status, $this->profileStatuses, true)) {
            throw new ValidationException(['status' => ['status must be onboarding, active, paused or suspended.']]);
        }
        $trustTier = strtolower(trim((string) ($body['trust_tier'] ?? ($profile['trust_tier'] ?? 'new')))) ?: 'new';
        $stmt = $this->pdo->prepare('UPDATE platform_maker_profiles SET status = :status, trust_tier = :trust_tier, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $status, 'trust_tier' => $trustTier, 'updated_at' => gmdate('c'), 'id' => $id]);
        $this->audit('maker_profile_status_updated', 'maker_profile', $id, sprintf('Maker profile moved to %s.', $status), ['trust_tier' => $trustTier], $userId);
        return $this->requireMakerProfile($id);
    }

    /** @return list<array<string, mixed>> */
    public function modelAssets(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT m.*, p.maker_ref, p.display_name AS maker_name, l.name AS license_name, l.royalty_credits_per_download FROM platform_model_assets m JOIN platform_maker_profiles p ON p.id = m.maker_profile_id LEFT JOIN platform_model_licenses l ON l.license_key = m.license_key';
        $params = [];
        if (in_array($status, $this->modelStatuses, true)) {
            $sql .= ' WHERE m.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY CASE m.status WHEN \'approved\' THEN 1 WHEN \'in_review\' THEN 2 WHEN \'submitted\' THEN 3 ELSE 4 END, m.updated_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeModelAsset'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function submitModelAsset(array $body, ?string $userId): array
    {
        $makerProfileId = trim((string) ($body['maker_profile_id'] ?? ''));
        if ($makerProfileId === '') {
            $profile = $this->firstMakerProfile();
            if ($profile === null) {
                throw new ValidationException(['maker_profile_id' => ['No maker profile exists. Create one first.']]);
            }
            $makerProfileId = (string) $profile['id'];
        }
        $this->requireMakerProfile($makerProfileId);

        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(['title' => ['title is required.']]);
        }
        $repairUseCase = trim((string) ($body['repair_use_case'] ?? ''));
        if ($repairUseCase === '') {
            throw new ValidationException(['repair_use_case' => ['repair_use_case is required.']]);
        }

        $licenseKey = trim((string) ($body['license_key'] ?? 'repair_credit_pilot')) ?: 'repair_credit_pilot';
        $this->requireLicenseByKey($licenseKey);
        $status = strtolower(trim((string) ($body['status'] ?? 'submitted')));
        if (!in_array($status, $this->modelStatuses, true)) {
            throw new ValidationException(['status' => ['status must be submitted, in_review, approved, rejected or retired.']]);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_model_assets (id, maker_profile_id, title, object_category, repair_use_case, status, license_key, file_kind, quality_score, safety_notes, metadata_json, submitted_by, reviewed_by, reviewed_at, created_at, updated_at) VALUES (:id, :maker_profile_id, :title, :object_category, :repair_use_case, :status, :license_key, :file_kind, :quality_score, :safety_notes, :metadata_json, :submitted_by, :reviewed_by, :reviewed_at, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'maker_profile_id' => $makerProfileId,
            'title' => $title,
            'object_category' => trim((string) ($body['object_category'] ?? 'repair_part')) ?: 'repair_part',
            'repair_use_case' => $repairUseCase,
            'status' => $status,
            'license_key' => $licenseKey,
            'file_kind' => strtolower(trim((string) ($body['file_kind'] ?? 'stl'))) ?: 'stl',
            'quality_score' => max(0, min(100, (int) ($body['quality_score'] ?? 0))),
            'safety_notes' => trim((string) ($body['safety_notes'] ?? '')) ?: null,
            'metadata_json' => json_encode($body['metadata'] ?? ['source' => 'api'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'submitted_by' => $userId,
            'reviewed_by' => in_array($status, ['approved', 'rejected'], true) ? $userId : null,
            'reviewed_at' => in_array($status, ['approved', 'rejected'], true) ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('model_asset_submitted', 'model_asset', $id, sprintf('Model asset submitted: %s.', $title), ['status' => $status, 'license_key' => $licenseKey], $userId);
        return $this->requireModelAsset($id);
    }

    /** @return array<string, mixed> */
    public function reviewModelAsset(string $id, array $body, ?string $userId): array
    {
        $this->requireModelAsset($id);
        $status = strtolower(trim((string) ($body['status'] ?? 'approved')));
        if (!in_array($status, ['in_review', 'approved', 'rejected', 'retired'], true)) {
            throw new ValidationException(['status' => ['status must be in_review, approved, rejected or retired.']]);
        }
        $qualityScore = max(0, min(100, (int) ($body['quality_score'] ?? 0)));
        $stmt = $this->pdo->prepare('UPDATE platform_model_assets SET status = :status, quality_score = CASE WHEN :quality_score_flag > 0 THEN :quality_score ELSE quality_score END, safety_notes = COALESCE(:safety_notes, safety_notes), reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'quality_score_flag' => $qualityScore,
            'quality_score' => $qualityScore,
            'safety_notes' => trim((string) ($body['safety_notes'] ?? '')) ?: null,
            'reviewed_by' => $userId,
            'reviewed_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'id' => $id,
        ]);
        $this->audit('model_asset_reviewed', 'model_asset', $id, sprintf('Model asset reviewed as %s.', $status), ['quality_score' => $qualityScore], $userId);
        return $this->requireModelAsset($id);
    }

    /** @return list<array<string, mixed>> */
    public function modelLicenses(string $status = 'all'): array
    {
        if (in_array($status, ['active', 'draft', 'retired'], true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_model_licenses WHERE status = :status ORDER BY license_key ASC');
            $stmt->execute(['status' => $status]);
            return array_map([$this, 'normalizeModelLicense'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        $stmt = $this->pdo->query('SELECT * FROM platform_model_licenses ORDER BY CASE status WHEN \'active\' THEN 1 WHEN \'draft\' THEN 2 ELSE 3 END, license_key ASC');
        return array_map([$this, 'normalizeModelLicense'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function modelDownloads(int $limit = 50, ?string $modelAssetId = null): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT d.*, m.title AS model_title, p.display_name AS maker_name, l.license_key FROM platform_model_downloads d JOIN platform_model_assets m ON m.id = d.model_asset_id JOIN platform_maker_profiles p ON p.id = m.maker_profile_id LEFT JOIN platform_model_licenses l ON l.id = d.license_id';
        $params = [];
        if ($modelAssetId !== null && $modelAssetId !== '') {
            $sql .= ' WHERE d.model_asset_id = :model_asset_id';
            $params['model_asset_id'] = $modelAssetId;
        }
        $sql .= ' ORDER BY d.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeModelDownload'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function recordModelDownload(array $body, ?string $userId): array
    {
        $modelAssetId = trim((string) ($body['model_asset_id'] ?? ''));
        if ($modelAssetId === '') {
            $asset = $this->firstApprovedModelAsset();
            if ($asset === null) {
                throw new ValidationException(['model_asset_id' => ['No approved model asset exists.']]);
            }
            $modelAssetId = (string) $asset['id'];
        }
        $asset = $this->requireModelAsset($modelAssetId);
        if (($asset['status'] ?? '') !== 'approved') {
            throw new ValidationException(['model_asset_id' => ['Only approved model assets can be downloaded in pilot mode.']]);
        }
        $license = $this->requireLicenseByKey((string) $asset['license_key']);
        if (($license['status'] ?? '') !== 'active') {
            throw new ValidationException(['license_key' => ['The model license is not active.']]);
        }

        $downloaderRef = trim((string) ($body['downloader_ref'] ?? 'repair-user-demo')) ?: 'repair-user-demo';
        $royaltyCredits = max(0, (int) ($license['royalty_credits_per_download'] ?? 0));
        $downloadId = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_model_downloads (id, model_asset_id, license_id, downloader_type, downloader_ref, purpose, status, credits_charged, royalty_credits, recorded_by, created_at) VALUES (:id, :model_asset_id, :license_id, :downloader_type, :downloader_ref, :purpose, :status, :credits_charged, :royalty_credits, :recorded_by, :created_at)');
        $stmt->execute([
            'id' => $downloadId,
            'model_asset_id' => $modelAssetId,
            'license_id' => $license['id'],
            'downloader_type' => strtolower(trim((string) ($body['downloader_type'] ?? 'repair_user'))) ?: 'repair_user',
            'downloader_ref' => $downloaderRef,
            'purpose' => trim((string) ($body['purpose'] ?? 'repair_attempt')) ?: 'repair_attempt',
            'status' => 'recorded',
            'credits_charged' => max(0, (int) ($body['credits_charged'] ?? 0)),
            'royalty_credits' => $royaltyCredits,
            'recorded_by' => $userId,
            'created_at' => $now,
        ]);

        $creditTxnId = null;
        if ($royaltyCredits > 0) {
            $profile = $this->requireMakerProfile((string) $asset['maker_profile_id']);
            if (!empty($profile['credit_account_id'])) {
                $creditTxnId = $this->postCreditTransaction((string) $profile['credit_account_id'], 'royalty', $royaltyCredits, 'model_download', $downloadId, sprintf('Royalty credits for model download: %s.', $asset['title']), $userId);
            }
        }

        $royaltyId = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_model_royalty_events (id, model_asset_id, maker_profile_id, download_id, credit_transaction_id, royalty_type, credits_awarded, amount_cents, status, notes, created_at) VALUES (:id, :model_asset_id, :maker_profile_id, :download_id, :credit_transaction_id, :royalty_type, :credits_awarded, :amount_cents, :status, :notes, :created_at)');
        $stmt->execute([
            'id' => $royaltyId,
            'model_asset_id' => $modelAssetId,
            'maker_profile_id' => (string) $asset['maker_profile_id'],
            'download_id' => $downloadId,
            'credit_transaction_id' => $creditTxnId,
            'royalty_type' => 'download_credit',
            'credits_awarded' => $royaltyCredits,
            'amount_cents' => max(0, (int) ($license['royalty_cents_per_download'] ?? 0)),
            'status' => 'posted',
            'notes' => 'Local pilot royalty event; no cash payout was triggered.',
            'created_at' => $now,
        ]);

        $this->audit('model_download_recorded', 'model_download', $downloadId, sprintf('Model download recorded for %s.', $asset['title']), ['royalty_credits' => $royaltyCredits, 'royalty_event_id' => $royaltyId], $userId);
        return [
            'download' => $this->modelDownloads(1, $modelAssetId)[0] ?? null,
            'royalty_event' => $this->requireRoyaltyEvent($royaltyId),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function royaltyEvents(int $limit = 50, ?string $makerProfileId = null): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*, m.title AS model_title, p.display_name AS maker_name FROM platform_model_royalty_events r JOIN platform_model_assets m ON m.id = r.model_asset_id JOIN platform_maker_profiles p ON p.id = r.maker_profile_id';
        $params = [];
        if ($makerProfileId !== null && $makerProfileId !== '') {
            $sql .= ' WHERE r.maker_profile_id = :maker_profile_id';
            $params['maker_profile_id'] = $makerProfileId;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeRoyaltyEvent'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function repairBounties(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT b.*, COUNT(s.id) AS submission_count FROM platform_repair_bounties b LEFT JOIN platform_bounty_submissions s ON s.bounty_id = b.id';
        $params = [];
        if ($status === 'active') {
            $sql .= " WHERE b.status IN ('open', 'in_review')";
        } elseif (in_array($status, $this->bountyStatuses, true)) {
            $sql .= ' WHERE b.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' GROUP BY b.id ORDER BY CASE b.priority WHEN \'critical\' THEN 1 WHEN \'high\' THEN 2 WHEN \'normal\' THEN 3 ELSE 4 END, b.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeRepairBounty'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createRepairBounty(array $body, ?string $userId): array
    {
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(['title' => ['title is required.']]);
        }
        $problem = trim((string) ($body['problem_statement'] ?? ''));
        if ($problem === '') {
            throw new ValidationException(['problem_statement' => ['problem_statement is required.']]);
        }
        $status = strtolower(trim((string) ($body['status'] ?? 'open')));
        if (!in_array($status, $this->bountyStatuses, true)) {
            throw new ValidationException(['status' => ['status must be draft, open, in_review, awarded, closed or cancelled.']]);
        }
        $id = Uuid::v4();
        $code = strtoupper(trim((string) ($body['bounty_code'] ?? '')));
        if ($code === '') {
            $code = 'BOUNTY-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_repair_bounties (id, bounty_code, title, object_category, problem_statement, reward_credits, reward_cents, status, priority, source_type, source_ref, due_at, created_by, created_at, updated_at) VALUES (:id, :bounty_code, :title, :object_category, :problem_statement, :reward_credits, :reward_cents, :status, :priority, :source_type, :source_ref, :due_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'bounty_code' => $code,
            'title' => $title,
            'object_category' => trim((string) ($body['object_category'] ?? 'repair_part')) ?: 'repair_part',
            'problem_statement' => $problem,
            'reward_credits' => max(0, (int) ($body['reward_credits'] ?? 0)),
            'reward_cents' => max(0, (int) ($body['reward_cents'] ?? 0)),
            'status' => $status,
            'priority' => strtolower(trim((string) ($body['priority'] ?? 'normal'))) ?: 'normal',
            'source_type' => trim((string) ($body['source_type'] ?? 'ops')) ?: 'ops',
            'source_ref' => trim((string) ($body['source_ref'] ?? '')) ?: null,
            'due_at' => trim((string) ($body['due_at'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('repair_bounty_created', 'repair_bounty', $id, sprintf('Repair bounty created: %s.', $title), ['reward_credits' => max(0, (int) ($body['reward_credits'] ?? 0))], $userId);
        return $this->requireRepairBounty($id);
    }

    /** @return list<array<string, mixed>> */
    public function bountySubmissions(?string $bountyId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT s.*, b.title AS bounty_title, b.bounty_code, p.display_name AS maker_name, m.title AS model_title FROM platform_bounty_submissions s JOIN platform_repair_bounties b ON b.id = s.bounty_id JOIN platform_maker_profiles p ON p.id = s.maker_profile_id LEFT JOIN platform_model_assets m ON m.id = s.model_asset_id';
        $params = [];
        if ($bountyId !== null && $bountyId !== '') {
            $sql .= ' WHERE s.bounty_id = :bounty_id';
            $params['bounty_id'] = $bountyId;
        }
        $sql .= ' ORDER BY s.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeBountySubmission'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function submitBounty(array $body, ?string $userId): array
    {
        $bountyId = trim((string) ($body['bounty_id'] ?? ''));
        if ($bountyId === '') {
            $bounty = $this->firstOpenBounty();
            if ($bounty === null) {
                throw new ValidationException(['bounty_id' => ['No open repair bounty exists.']]);
            }
            $bountyId = (string) $bounty['id'];
        }
        $bounty = $this->requireRepairBounty($bountyId);
        if (!in_array((string) $bounty['status'], ['open', 'in_review'], true)) {
            throw new ValidationException(['bounty_id' => ['Bounty must be open or in_review.']]);
        }

        $makerProfileId = trim((string) ($body['maker_profile_id'] ?? ''));
        if ($makerProfileId === '') {
            $profile = $this->firstMakerProfile();
            if ($profile === null) {
                throw new ValidationException(['maker_profile_id' => ['No maker profile exists.']]);
            }
            $makerProfileId = (string) $profile['id'];
        }
        $this->requireMakerProfile($makerProfileId);

        $notes = trim((string) ($body['submission_notes'] ?? ''));
        if ($notes === '') {
            throw new ValidationException(['submission_notes' => ['submission_notes is required.']]);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_bounty_submissions (id, bounty_id, maker_profile_id, model_asset_id, status, submission_notes, review_notes, awarded_credits, awarded_cents, submitted_by, reviewed_by, reviewed_at, created_at, updated_at) VALUES (:id, :bounty_id, :maker_profile_id, :model_asset_id, :status, :submission_notes, NULL, 0, 0, :submitted_by, NULL, NULL, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'bounty_id' => $bountyId,
            'maker_profile_id' => $makerProfileId,
            'model_asset_id' => trim((string) ($body['model_asset_id'] ?? '')) ?: null,
            'status' => 'submitted',
            'submission_notes' => $notes,
            'submitted_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->pdo->prepare("UPDATE platform_repair_bounties SET status = 'in_review', updated_at = :updated_at WHERE id = :id AND status = 'open'")->execute(['updated_at' => $now, 'id' => $bountyId]);
        $this->audit('bounty_submission_created', 'bounty_submission', $id, sprintf('Submission created for bounty %s.', $bounty['bounty_code']), [], $userId);
        return $this->requireBountySubmission($id);
    }

    /** @return array<string, mixed> */
    public function reviewBountySubmission(string $id, array $body, ?string $userId): array
    {
        $submission = $this->requireBountySubmission($id);
        $status = strtolower(trim((string) ($body['status'] ?? 'accepted')));
        if (!in_array($status, ['in_review', 'accepted', 'rejected'], true)) {
            throw new ValidationException(['status' => ['status must be in_review, accepted or rejected.']]);
        }
        $bounty = $this->requireRepairBounty((string) $submission['bounty_id']);
        $awardedCredits = $status === 'accepted' ? max(0, (int) ($body['awarded_credits'] ?? $bounty['reward_credits'] ?? 0)) : 0;
        $awardedCents = $status === 'accepted' ? max(0, (int) ($body['awarded_cents'] ?? $bounty['reward_cents'] ?? 0)) : 0;
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_bounty_submissions SET status = :status, review_notes = :review_notes, awarded_credits = :awarded_credits, awarded_cents = :awarded_cents, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'review_notes' => trim((string) ($body['review_notes'] ?? '')) ?: null,
            'awarded_credits' => $awardedCredits,
            'awarded_cents' => $awardedCents,
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);

        if ($status === 'accepted') {
            $profile = $this->requireMakerProfile((string) $submission['maker_profile_id']);
            if ($awardedCredits > 0 && !empty($profile['credit_account_id'])) {
                $this->postCreditTransaction((string) $profile['credit_account_id'], 'bonus', $awardedCredits, 'repair_bounty', $id, sprintf('Repair bounty award: %s.', $bounty['title']), $userId);
            }
            $this->pdo->prepare("UPDATE platform_repair_bounties SET status = 'awarded', updated_at = :updated_at WHERE id = :id")->execute(['updated_at' => $now, 'id' => $submission['bounty_id']]);
        }

        $this->audit('bounty_submission_reviewed', 'bounty_submission', $id, sprintf('Bounty submission reviewed as %s.', $status), ['awarded_credits' => $awardedCredits, 'awarded_cents' => $awardedCents], $userId);
        return $this->requireBountySubmission($id);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_maker_economy_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAuditLog'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    private function operatorActions(): array
    {
        return [
            'create_maker_profile' => 'Register a maker profile and connect it to a repair credit account.',
            'submit_model_asset' => 'Register a model as a repair asset, not as a generic STL listing.',
            'review_model_asset' => 'Approve, reject or retire a model based on safety and repair usefulness.',
            'record_model_download' => 'Record a controlled pilot download and post local credit royalty events.',
            'create_repair_bounty' => 'Open a bounty for a real object repair problem that needs a maker solution.',
            'review_bounty_submission' => 'Accept a maker solution and award local repair credits when evidence is sufficient.',
        ];
    }

    private function ensureCreditAccount(string $makerRef, string $displayName, ?string $userId): string
    {
        $stmt = $this->pdo->prepare("SELECT id FROM platform_credit_accounts WHERE owner_type = 'maker' AND owner_ref = :owner_ref LIMIT 1");
        $stmt->execute(['owner_ref' => $makerRef]);
        $existing = $stmt->fetchColumn();
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_credit_accounts (id, owner_type, owner_ref, display_name, status, balance_credits, lifetime_earned_credits, lifetime_spent_credits, notes, created_by, created_at, updated_at) VALUES (:id, \'maker\', :owner_ref, :display_name, \'active\', 0, 0, 0, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'owner_ref' => $makerRef,
            'display_name' => $displayName,
            'notes' => 'Auto-created by Maker Economy onboarding workflow.',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $id;
    }

    private function postCreditTransaction(string $accountId, string $type, int $amount, string $sourceType, string $sourceId, string $description, ?string $userId): string
    {
        $account = $this->creditAccount($accountId);
        $balance = (int) ($account['balance_credits'] ?? 0) + $amount;
        if ($balance < 0) {
            throw new ValidationException(['amount_credits' => ['Credit balance cannot go negative.']]);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_credit_transactions (id, account_id, transaction_type, amount_credits, balance_after_credits, source_type, source_id, description, status, created_by, created_at) VALUES (:id, :account_id, :transaction_type, :amount_credits, :balance_after_credits, :source_type, :source_id, :description, \'posted\', :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'account_id' => $accountId,
            'transaction_type' => $type,
            'amount_credits' => $amount,
            'balance_after_credits' => $balance,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'created_by' => $userId,
            'created_at' => $now,
        ]);
        $earned = $amount > 0 ? $amount : 0;
        $spent = $amount < 0 ? abs($amount) : 0;
        $stmt = $this->pdo->prepare('UPDATE platform_credit_accounts SET balance_credits = :balance, lifetime_earned_credits = lifetime_earned_credits + :earned, lifetime_spent_credits = lifetime_spent_credits + :spent, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['balance' => $balance, 'earned' => $earned, 'spent' => $spent, 'updated_at' => $now, 'id' => $accountId]);
        return $id;
    }

    /** @return array<string, mixed> */
    private function creditAccount(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_credit_accounts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Credit account not found.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireMakerProfile(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, ca.balance_credits, ca.lifetime_earned_credits, pa.status AS payout_status FROM platform_maker_profiles p LEFT JOIN platform_credit_accounts ca ON ca.id = p.credit_account_id LEFT JOIN platform_payout_accounts pa ON pa.id = p.payout_account_id WHERE p.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Maker profile not found.');
        }
        return $this->normalizeMakerProfile($row);
    }

    /** @return array<string, mixed>|null */
    private function firstMakerProfile(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM platform_maker_profiles WHERE status IN ('active', 'onboarding') ORDER BY CASE status WHEN 'active' THEN 1 ELSE 2 END, created_at ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function requireModelAsset(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT m.*, p.maker_ref, p.display_name AS maker_name, l.name AS license_name, l.royalty_credits_per_download FROM platform_model_assets m JOIN platform_maker_profiles p ON p.id = m.maker_profile_id LEFT JOIN platform_model_licenses l ON l.license_key = m.license_key WHERE m.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Model asset not found.');
        }
        return $this->normalizeModelAsset($row);
    }

    /** @return array<string, mixed>|null */
    private function firstApprovedModelAsset(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM platform_model_assets WHERE status = 'approved' ORDER BY created_at ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function requireLicenseByKey(string $licenseKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_model_licenses WHERE license_key = :license_key');
        $stmt->execute(['license_key' => $licenseKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Model license not found.');
        }
        return $this->normalizeModelLicense($row);
    }

    /** @return array<string, mixed> */
    private function requireRoyaltyEvent(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, m.title AS model_title, p.display_name AS maker_name FROM platform_model_royalty_events r JOIN platform_model_assets m ON m.id = r.model_asset_id JOIN platform_maker_profiles p ON p.id = r.maker_profile_id WHERE r.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Royalty event not found.');
        }
        return $this->normalizeRoyaltyEvent($row);
    }

    /** @return array<string, mixed> */
    private function requireRepairBounty(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT b.*, COUNT(s.id) AS submission_count FROM platform_repair_bounties b LEFT JOIN platform_bounty_submissions s ON s.bounty_id = b.id WHERE b.id = :id GROUP BY b.id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Repair bounty not found.');
        }
        return $this->normalizeRepairBounty($row);
    }

    /** @return array<string, mixed>|null */
    private function firstOpenBounty(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM platform_repair_bounties WHERE status = 'open' ORDER BY CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 ELSE 3 END, created_at ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function requireBountySubmission(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT s.*, b.title AS bounty_title, b.bounty_code, p.display_name AS maker_name, m.title AS model_title FROM platform_bounty_submissions s JOIN platform_repair_bounties b ON b.id = s.bounty_id JOIN platform_maker_profiles p ON p.id = s.maker_profile_id LEFT JOIN platform_model_assets m ON m.id = s.model_asset_id WHERE s.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Bounty submission not found.');
        }
        return $this->normalizeBountySubmission($row);
    }

    private function audit(string $action, string $subjectType, ?string $subjectId, string $message, array $metadata, ?string $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_maker_economy_audit_log (id, action, subject_type, subject_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :subject_type, :subject_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'message' => $message,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s%s', $table, $where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function sum(string $table, string $column, ?string $where = null): int
    {
        $sql = sprintf('SELECT COALESCE(SUM(%s), 0) FROM %s%s', $column, $table, $where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== ''));
    }

    /** @return array<string, mixed> */
    private function normalizeMakerProfile(array $row): array
    {
        $row['specialty_tags'] = json_decode((string) ($row['specialty_tags_json'] ?? '[]'), true) ?: [];
        unset($row['specialty_tags_json']);
        foreach (['balance_credits', 'lifetime_earned_credits'] as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $row[$key] === null ? null : (int) $row[$key];
            }
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeModelAsset(array $row): array
    {
        $row['quality_score'] = (int) ($row['quality_score'] ?? 0);
        $row['metadata'] = json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [];
        unset($row['metadata_json']);
        if (array_key_exists('royalty_credits_per_download', $row) && $row['royalty_credits_per_download'] !== null) {
            $row['royalty_credits_per_download'] = (int) $row['royalty_credits_per_download'];
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeModelLicense(array $row): array
    {
        foreach (['requires_attribution', 'commercial_use_allowed'] as $key) {
            $row[$key] = ((int) ($row[$key] ?? 0)) === 1;
        }
        foreach (['royalty_credits_per_download', 'royalty_cents_per_download'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeModelDownload(array $row): array
    {
        foreach (['credits_charged', 'royalty_credits'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeRoyaltyEvent(array $row): array
    {
        foreach (['credits_awarded', 'amount_cents'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeRepairBounty(array $row): array
    {
        foreach (['reward_credits', 'reward_cents', 'submission_count'] as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = (int) ($row[$key] ?? 0);
            }
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeBountySubmission(array $row): array
    {
        foreach (['awarded_credits', 'awarded_cents'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeAuditLog(array $row): array
    {
        $row['metadata'] = json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [];
        unset($row['metadata_json']);
        return $row;
    }
}
