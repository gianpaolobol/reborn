<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class MarketplaceRevenueService
{
    /** @var list<string> */
    private array $accountStatuses = ['active', 'pending', 'paused', 'blocked'];

    /** @var list<string> */
    private array $payoutStatuses = ['draft', 'evaluated', 'approved', 'paid', 'cancelled'];

    /** @var list<string> */
    private array $beneficiaryTypes = ['provider', 'maker', 'partner', 'enterprise'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'marketplace_revenue_version' => 'marketplace_revenue_credits_payout_governance_v1_step28',
            'generated_at' => gmdate('c'),
            'summary' => $this->summary(),
            'fee_policies' => $this->feePolicies('all'),
            'credit_accounts' => $this->creditAccounts('all', 20),
            'recent_credit_transactions' => $this->creditTransactions(20),
            'payout_accounts' => $this->payoutAccounts('all', 20),
            'payout_runs' => $this->payoutRuns('active', 10),
            'recent_payout_items' => $this->payoutItems(null, 20),
            'operator_actions' => $this->operatorActions(),
            'important_notes' => [
                'Credits, payout runs and fee policies are governance records only; no real money is moved by Step 28.',
                'Real Stripe/PayPal settlement, tax invoices, refunds and KYC/KYB remain blocked until legal/payment controls are approved.',
                'Maker economy is represented as a controlled ledger foundation, not as public model downloads or automated royalties yet.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'active_fee_policies' => $this->count('platform_marketplace_fee_policies', "status = 'active'"),
            'draft_fee_policies' => $this->count('platform_marketplace_fee_policies', "status = 'draft'"),
            'credit_accounts_total' => $this->count('platform_credit_accounts'),
            'credit_accounts_active' => $this->count('platform_credit_accounts', "status = 'active'"),
            'credits_balance_total' => $this->sum('platform_credit_accounts', 'balance_credits'),
            'credits_lifetime_earned' => $this->sum('platform_credit_accounts', 'lifetime_earned_credits'),
            'payout_accounts_total' => $this->count('platform_payout_accounts'),
            'payout_accounts_active' => $this->count('platform_payout_accounts', "status = 'active'"),
            'payout_runs_active' => $this->count('platform_payout_runs', "status IN ('draft', 'evaluated', 'approved')"),
            'payout_runs_paid' => $this->count('platform_payout_runs', "status = 'paid'"),
            'evaluated_payout_cents' => $this->sum('platform_payout_runs', 'payout_amount_cents', "status IN ('evaluated', 'approved', 'paid')"),
            'platform_fee_cents_recorded' => $this->sum('platform_payout_runs', 'platform_fee_cents', "status IN ('evaluated', 'approved', 'paid')"),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function feePolicies(string $status = 'all'): array
    {
        if (in_array($status, ['active', 'draft', 'retired'], true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_marketplace_fee_policies WHERE status = :status ORDER BY scope ASC, applies_to ASC, fee_key ASC');
            $stmt->execute(['status' => $status]);
            return array_map([$this, 'normalizeFeePolicy'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->query("SELECT * FROM platform_marketplace_fee_policies ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END, scope ASC, applies_to ASC, fee_key ASC");
        return array_map([$this, 'normalizeFeePolicy'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function creditAccounts(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if (in_array($status, $this->accountStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_credit_accounts WHERE status = :status ORDER BY balance_credits DESC, updated_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizeCreditAccount'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare('SELECT * FROM platform_credit_accounts ORDER BY balance_credits DESC, updated_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeCreditAccount'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createCreditAccount(array $body, ?string $userId): array
    {
        $ownerType = strtolower(trim((string) ($body['owner_type'] ?? 'maker')));
        if (!in_array($ownerType, $this->beneficiaryTypes, true)) {
            throw new ValidationException(['owner_type' => ['owner_type must be provider, maker, partner or enterprise.']]);
        }

        $ownerRef = trim((string) ($body['owner_ref'] ?? ''));
        if ($ownerRef === '') {
            throw new ValidationException(['owner_ref' => ['owner_ref is required.']]);
        }

        $displayName = trim((string) ($body['display_name'] ?? $ownerRef));
        $status = strtolower(trim((string) ($body['status'] ?? 'active')));
        if (!in_array($status, $this->accountStatuses, true)) {
            throw new ValidationException(['status' => ['status must be active, pending, paused or blocked.']]);
        }

        $opening = max(0, (int) ($body['opening_balance_credits'] ?? 0));
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_credit_accounts (id, owner_type, owner_ref, display_name, status, balance_credits, lifetime_earned_credits, lifetime_spent_credits, notes, created_by, created_at, updated_at) VALUES (:id, :owner_type, :owner_ref, :display_name, :status, :balance, :earned, 0, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'owner_type' => $ownerType,
            'owner_ref' => $ownerRef,
            'display_name' => $displayName,
            'status' => $status,
            'balance' => 0,
            'earned' => 0,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($opening > 0) {
            $this->insertCreditTransaction($id, 'grant', $opening, 'opening_balance', $id, 'Opening balance for pilot credit account.', $userId);
        }

        $this->audit('credit_account_created', 'credit_account', $id, sprintf('Credit account created for %s:%s.', $ownerType, $ownerRef), ['opening_balance_credits' => $opening], $userId);
        return $this->requireCreditAccount($id);
    }

    /** @return list<array<string, mixed>> */
    public function creditTransactions(int $limit = 50, ?string $accountId = null): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT t.*, a.owner_type, a.owner_ref, a.display_name FROM platform_credit_transactions t JOIN platform_credit_accounts a ON a.id = t.account_id';
        $params = [];
        if ($accountId !== null && $accountId !== '') {
            $sql .= ' WHERE t.account_id = :account_id';
            $params['account_id'] = $accountId;
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeCreditTransaction'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function recordCreditTransaction(array $body, ?string $userId): array
    {
        $accountId = trim((string) ($body['account_id'] ?? ''));
        if ($accountId === '') {
            $account = $this->firstCreditAccount();
            if ($account === null) {
                throw new ValidationException(['account_id' => ['No credit account exists. Create one first.']]);
            }
            $accountId = (string) $account['id'];
        }
        $account = $this->requireCreditAccount($accountId);

        $type = strtolower(trim((string) ($body['transaction_type'] ?? 'grant')));
        if (!in_array($type, ['grant', 'royalty', 'bonus', 'refund', 'adjustment', 'debit'], true)) {
            throw new ValidationException(['transaction_type' => ['transaction_type must be grant, royalty, bonus, refund, adjustment or debit.']]);
        }

        $amount = (int) ($body['amount_credits'] ?? 0);
        if ($amount === 0) {
            throw new ValidationException(['amount_credits' => ['amount_credits must not be zero.']]);
        }
        if ($type === 'debit' && $amount > 0) {
            $amount *= -1;
        }

        if (((int) $account['balance_credits'] + $amount) < 0) {
            throw new ValidationException(['amount_credits' => ['Credit balance cannot go negative in pilot governance mode.']]);
        }

        $sourceType = trim((string) ($body['source_type'] ?? 'manual')) ?: 'manual';
        $sourceId = trim((string) ($body['source_id'] ?? '')) ?: null;
        $description = trim((string) ($body['description'] ?? sprintf('Manual %s credit transaction.', $type)));
        $transaction = $this->insertCreditTransaction((string) $account['id'], $type, $amount, $sourceType, $sourceId, $description, $userId);
        $this->audit('credit_transaction_recorded', 'credit_account', (string) $account['id'], sprintf('Credit transaction %s recorded for %s credits.', $type, (string) $amount), ['transaction_id' => $transaction['id']], $userId);
        return $transaction;
    }

    /** @return list<array<string, mixed>> */
    public function payoutAccounts(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if (in_array($status, $this->accountStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_accounts WHERE status = :status ORDER BY updated_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizePayoutAccount'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_accounts ORDER BY CASE status WHEN \'active\' THEN 1 WHEN \'pending\' THEN 2 ELSE 3 END, updated_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizePayoutAccount'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createPayoutAccount(array $body, ?string $userId): array
    {
        $type = strtolower(trim((string) ($body['beneficiary_type'] ?? 'provider')));
        if (!in_array($type, $this->beneficiaryTypes, true)) {
            throw new ValidationException(['beneficiary_type' => ['beneficiary_type must be provider, maker, partner or enterprise.']]);
        }
        $ref = trim((string) ($body['beneficiary_ref'] ?? ''));
        if ($ref === '') {
            throw new ValidationException(['beneficiary_ref' => ['beneficiary_ref is required.']]);
        }
        $displayName = trim((string) ($body['display_name'] ?? $ref));
        $status = strtolower(trim((string) ($body['status'] ?? 'pending')));
        if (!in_array($status, $this->accountStatuses, true)) {
            throw new ValidationException(['status' => ['status must be active, pending, paused or blocked.']]);
        }
        $currency = strtoupper(trim((string) ($body['currency'] ?? 'EUR'))) ?: 'EUR';
        if (!in_array($currency, ['EUR', 'USD', 'GBP'], true)) {
            throw new ValidationException(['currency' => ['Only EUR, USD and GBP are supported in pilot governance.']]);
        }
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_payout_accounts (id, beneficiary_type, beneficiary_ref, display_name, status, payout_method, currency, hold_days, minimum_payout_cents, notes, created_by, created_at, updated_at) VALUES (:id, :beneficiary_type, :beneficiary_ref, :display_name, :status, :payout_method, :currency, :hold_days, :minimum_payout_cents, :notes, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'beneficiary_type' => $type,
            'beneficiary_ref' => $ref,
            'display_name' => $displayName,
            'status' => $status,
            'payout_method' => trim((string) ($body['payout_method'] ?? 'mock_manual')) ?: 'mock_manual',
            'currency' => $currency,
            'hold_days' => max(0, (int) ($body['hold_days'] ?? 7)),
            'minimum_payout_cents' => max(0, (int) ($body['minimum_payout_cents'] ?? 2500)),
            'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('payout_account_created', 'payout_account', $id, sprintf('Payout account created for %s:%s.', $type, $ref), [], $userId);
        return $this->requirePayoutAccount($id);
    }

    /** @return list<array<string, mixed>> */
    public function payoutRuns(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("SELECT * FROM platform_payout_runs WHERE status IN ('draft', 'evaluated', 'approved') ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizePayoutRun'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        if (in_array($status, $this->payoutStatuses, true)) {
            $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_runs WHERE status = :status ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('status', $status);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizePayoutRun'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_runs ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizePayoutRun'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function evaluatePayoutRun(array $body, ?string $userId): array
    {
        $currency = strtoupper(trim((string) ($body['currency'] ?? 'EUR'))) ?: 'EUR';
        if (!in_array($currency, ['EUR', 'USD', 'GBP'], true)) {
            throw new ValidationException(['currency' => ['Only EUR, USD and GBP are supported in pilot governance.']]);
        }
        $now = gmdate('c');
        $runId = Uuid::v4();
        $runCode = strtoupper(trim((string) ($body['run_code'] ?? '')));
        if ($runCode === '') {
            $runCode = 'PAYOUT-' . strtoupper(gmdate('Ymd-His'));
        }

        $stmt = $this->pdo->prepare('INSERT INTO platform_payout_runs (id, run_code, status, currency, period_start, period_end, notes, evaluated_by, evaluated_at, created_by, created_at, updated_at) VALUES (:id, :run_code, :status, :currency, :period_start, :period_end, :notes, :evaluated_by, :evaluated_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $runId,
            'run_code' => $runCode,
            'status' => 'evaluated',
            'currency' => $currency,
            'period_start' => trim((string) ($body['period_start'] ?? '')) ?: null,
            'period_end' => trim((string) ($body['period_end'] ?? '')) ?: $now,
            'notes' => trim((string) ($body['notes'] ?? '')) ?: 'Evaluated by Step 28 payout governance.',
            'evaluated_by' => $userId,
            'evaluated_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $items = $this->buildPayoutItemsFromMockAuthorizedOrders($runId, $currency);
        if ($items === []) {
            $items = $this->buildSeedPilotPayoutItems($runId, $currency);
        }

        $totals = $this->recalculatePayoutRunTotals($runId);
        $this->audit('payout_run_evaluated', 'payout_run', $runId, sprintf('Payout run %s evaluated with %d item(s).', $runCode, $totals['item_count']), $totals, $userId);

        return [
            'payout_run' => $this->requirePayoutRun($runId),
            'payout_items' => $this->payoutItems($runId, 100),
            'notes' => $items === [] ? ['No eligible payout items were found.'] : ['Payout run generated in mock governance mode.'],
        ];
    }

    /** @return array<string, mixed> */
    public function approvePayoutRun(string $idOrCode, array $body, ?string $userId): array
    {
        $run = $this->requirePayoutRun($idOrCode);
        if (!in_array($run['status'], ['draft', 'evaluated'], true)) {
            throw new ValidationException(['payout_run' => ['Only draft or evaluated payout runs can be approved.']]);
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_payout_runs SET status = :status, approved_by = :approved_by, approved_at = :approved_at, notes = :notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => $now,
            'notes' => trim((string) ($body['notes'] ?? 'Approved for mock manual payout.')),
            'updated_at' => $now,
            'id' => $run['id'],
        ]);
        $this->pdo->prepare("UPDATE platform_payout_items SET status = 'approved' WHERE payout_run_id = :id AND status = 'evaluated'")->execute(['id' => $run['id']]);
        $this->audit('payout_run_approved', 'payout_run', (string) $run['id'], sprintf('Payout run %s approved in mock governance mode.', $run['run_code']), [], $userId);
        return $this->requirePayoutRun((string) $run['id']);
    }

    /** @return array<string, mixed> */
    public function markPayoutRunPaid(string $idOrCode, array $body, ?string $userId): array
    {
        $run = $this->requirePayoutRun($idOrCode);
        if ($run['status'] !== 'approved') {
            throw new ValidationException(['payout_run' => ['Only approved payout runs can be marked paid.']]);
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_payout_runs SET status = :status, paid_by = :paid_by, paid_at = :paid_at, notes = :notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'paid',
            'paid_by' => $userId,
            'paid_at' => $now,
            'notes' => trim((string) ($body['notes'] ?? 'Marked as paid in mock manual payout workflow.')),
            'updated_at' => $now,
            'id' => $run['id'],
        ]);
        $this->pdo->prepare("UPDATE platform_payout_items SET status = 'paid' WHERE payout_run_id = :id")->execute(['id' => $run['id']]);
        $this->audit('payout_run_paid_mock', 'payout_run', (string) $run['id'], sprintf('Payout run %s marked paid. No real money moved.', $run['run_code']), ['mock_only' => true], $userId);
        return $this->requirePayoutRun((string) $run['id']);
    }

    /** @return list<array<string, mixed>> */
    public function payoutItems(?string $payoutRunId = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($payoutRunId !== null && $payoutRunId !== '') {
            $run = $this->requirePayoutRun($payoutRunId);
            $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_items WHERE payout_run_id = :run_id ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('run_id', $run['id']);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map([$this, 'normalizePayoutItem'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_items ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizePayoutItem'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_revenue_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAudit'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<string> */
    private function operatorActions(): array
    {
        $actions = [];
        if ($this->count('platform_marketplace_fee_policies', "status = 'active'") === 0) {
            $actions[] = 'Activate at least one marketplace fee policy before any real transaction.';
        }
        if ($this->count('platform_payout_accounts', "status = 'active'") === 0) {
            $actions[] = 'Activate at least one payout account for provider pilot testing.';
        }
        if ($this->count('platform_payout_runs', "status IN ('evaluated', 'approved')") === 0) {
            $actions[] = 'Evaluate a mock payout run to prove the marketplace ledger can be reviewed.';
        }
        if ($this->count('platform_credit_accounts', "status = 'active'") === 0) {
            $actions[] = 'Create at least one active credit account before maker economy testing.';
        }
        return $actions ?: ['Marketplace revenue governance baseline is configured for local/pilot review.'];
    }

    /** @return list<array<string, mixed>> */
    private function buildPayoutItemsFromMockAuthorizedOrders(string $runId, string $currency): array
    {
        $stmt = $this->pdo->prepare("SELECT ro.*, pi.id AS payment_intent_id, pi.confirmed_at, p.name AS provider_name FROM repair_orders ro JOIN payment_intents pi ON pi.repair_order_id = ro.id LEFT JOIN providers p ON p.id = ro.provider_id WHERE pi.status = 'mock_authorized' AND ro.currency = :currency AND NOT EXISTS (SELECT 1 FROM platform_payout_items existing WHERE existing.source_type = 'repair_order' AND existing.source_id = ro.id) ORDER BY pi.confirmed_at ASC LIMIT 50");
        $stmt->execute(['currency' => $currency]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $row) {
            $payoutAccount = $this->findPayoutAccount('provider', (string) $row['provider_id']);
            $item = $this->insertPayoutItem(
                $runId,
                $payoutAccount['id'] ?? null,
                'provider',
                (string) $row['provider_id'],
                (string) ($row['provider_name'] ?? $row['provider_id']),
                'repair_order',
                (string) $row['id'],
                (int) $row['total_cents'],
                (int) $row['platform_fee_cents'],
                (int) $row['provider_payout_cents'],
                0,
                [
                    'payment_intent_id' => $row['payment_intent_id'],
                    'confirmed_at' => $row['confirmed_at'],
                    'mock_only' => true,
                    'requires_manual_review' => true,
                ]
            );
            $items[] = $item;
        }
        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function buildSeedPilotPayoutItems(string $runId, string $currency): array
    {
        $items = [];
        $seedRows = [
            ['provider', 'provider-bologna-lab', 'Bologna Repair Lab', 'pilot_provider_minimum', 'provider-bologna-lab', 3970, 470, 3500, 0],
            ['maker', 'maker-demo-001', 'Maker Demo 001', 'pilot_maker_royalty', 'cad-demo-garmin-strap-connector', 0, 0, 0, 35],
        ];

        foreach ($seedRows as [$type, $ref, $display, $sourceType, $sourceId, $gross, $fee, $payout, $credits]) {
            $source = $sourceId . '-' . substr(Uuid::v4(), 0, 8);
            $payoutAccount = $this->findPayoutAccount((string) $type, (string) $ref);
            $items[] = $this->insertPayoutItem(
                $runId,
                $payoutAccount['id'] ?? null,
                (string) $type,
                (string) $ref,
                (string) $display,
                (string) $sourceType,
                $source,
                (int) $gross,
                (int) $fee,
                (int) $payout,
                (int) $credits,
                [
                    'currency' => $currency,
                    'seed_pilot_item' => true,
                    'mock_only' => true,
                    'reason' => 'No authorized repair orders were available; generated controlled seed evidence for Step 28 smoke/demo.',
                ]
            );
            if ((int) $credits > 0) {
                $account = $this->findCreditAccount((string) $type, (string) $ref);
                if ($account !== null) {
                    $this->insertCreditTransaction((string) $account['id'], 'royalty', (int) $credits, (string) $sourceType, $source, 'Pilot maker royalty credits from payout evaluation.', null);
                }
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function insertPayoutItem(string $runId, ?string $payoutAccountId, string $beneficiaryType, string $beneficiaryRef, string $displayName, string $sourceType, string $sourceId, int $grossCents, int $platformFeeCents, int $payoutCents, int $creditsEarned, array $evidence): array
    {
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO platform_payout_items (id, payout_run_id, payout_account_id, beneficiary_type, beneficiary_ref, display_name, source_type, source_id, gross_amount_cents, platform_fee_cents, payout_amount_cents, credits_earned, status, evidence_json, created_at) VALUES (:id, :payout_run_id, :payout_account_id, :beneficiary_type, :beneficiary_ref, :display_name, :source_type, :source_id, :gross_amount_cents, :platform_fee_cents, :payout_amount_cents, :credits_earned, :status, :evidence_json, :created_at)');
        $stmt->execute([
            'id' => $id,
            'payout_run_id' => $runId,
            'payout_account_id' => $payoutAccountId,
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_ref' => $beneficiaryRef,
            'display_name' => $displayName,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'gross_amount_cents' => $grossCents,
            'platform_fee_cents' => $platformFeeCents,
            'payout_amount_cents' => $payoutCents,
            'credits_earned' => $creditsEarned,
            'status' => 'evaluated',
            'evidence_json' => json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => gmdate('c'),
        ]);
        $row = $this->pdo->query("SELECT * FROM platform_payout_items WHERE id = " . $this->pdo->quote($id))->fetch(PDO::FETCH_ASSOC);
        return $this->normalizePayoutItem($row ?: []);
    }

    /** @return array<string, mixed> */
    private function recalculatePayoutRunTotals(string $runId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS item_count, COALESCE(SUM(gross_amount_cents), 0) AS gross, COALESCE(SUM(platform_fee_cents), 0) AS fees, COALESCE(SUM(payout_amount_cents), 0) AS payouts, COALESCE(SUM(credits_earned), 0) AS credits FROM platform_payout_items WHERE payout_run_id = :run_id');
        $stmt->execute(['run_id' => $runId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $data = [
            'item_count' => (int) ($totals['item_count'] ?? 0),
            'gross_amount_cents' => (int) ($totals['gross'] ?? 0),
            'platform_fee_cents' => (int) ($totals['fees'] ?? 0),
            'payout_amount_cents' => (int) ($totals['payouts'] ?? 0),
            'credits_amount' => (int) ($totals['credits'] ?? 0),
        ];
        $stmt = $this->pdo->prepare('UPDATE platform_payout_runs SET item_count = :item_count, gross_amount_cents = :gross, platform_fee_cents = :fees, payout_amount_cents = :payouts, credits_amount = :credits, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'item_count' => $data['item_count'],
            'gross' => $data['gross_amount_cents'],
            'fees' => $data['platform_fee_cents'],
            'payouts' => $data['payout_amount_cents'],
            'credits' => $data['credits_amount'],
            'updated_at' => gmdate('c'),
            'id' => $runId,
        ]);
        return $data;
    }

    /** @return array<string, mixed> */
    private function insertCreditTransaction(string $accountId, string $type, int $amount, string $sourceType, ?string $sourceId, string $description, ?string $userId): array
    {
        $account = $this->requireCreditAccount($accountId);
        $balance = (int) $account['balance_credits'] + $amount;
        $earnedIncrement = $amount > 0 ? $amount : 0;
        $spentIncrement = $amount < 0 ? abs($amount) : 0;
        $now = gmdate('c');
        $id = Uuid::v4();

        $stmt = $this->pdo->prepare('INSERT INTO platform_credit_transactions (id, account_id, transaction_type, amount_credits, balance_after_credits, source_type, source_id, description, status, created_by, created_at) VALUES (:id, :account_id, :transaction_type, :amount_credits, :balance_after_credits, :source_type, :source_id, :description, :status, :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'account_id' => $accountId,
            'transaction_type' => $type,
            'amount_credits' => $amount,
            'balance_after_credits' => $balance,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'status' => 'posted',
            'created_by' => $userId,
            'created_at' => $now,
        ]);
        $update = $this->pdo->prepare('UPDATE platform_credit_accounts SET balance_credits = :balance, lifetime_earned_credits = lifetime_earned_credits + :earned, lifetime_spent_credits = lifetime_spent_credits + :spent, updated_at = :updated_at WHERE id = :id');
        $update->execute([
            'balance' => $balance,
            'earned' => $earnedIncrement,
            'spent' => $spentIncrement,
            'updated_at' => $now,
            'id' => $accountId,
        ]);

        $stmt = $this->pdo->prepare('SELECT t.*, a.owner_type, a.owner_ref, a.display_name FROM platform_credit_transactions t JOIN platform_credit_accounts a ON a.id = t.account_id WHERE t.id = :id');
        $stmt->execute(['id' => $id]);
        return $this->normalizeCreditTransaction($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    private function firstCreditAccount(): ?array
    {
        $row = $this->pdo->query("SELECT * FROM platform_credit_accounts WHERE status = 'active' ORDER BY updated_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeCreditAccount($row) : null;
    }

    /** @return array<string, mixed>|null */
    private function findCreditAccount(string $ownerType, string $ownerRef): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_credit_accounts WHERE owner_type = :owner_type AND owner_ref = :owner_ref LIMIT 1');
        $stmt->execute(['owner_type' => $ownerType, 'owner_ref' => $ownerRef]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeCreditAccount($row) : null;
    }

    /** @return array<string, mixed> */
    private function requireCreditAccount(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_credit_accounts WHERE id = :id OR owner_ref = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Credit account not found.');
        }
        return $this->normalizeCreditAccount($row);
    }

    /** @return array<string, mixed>|null */
    private function findPayoutAccount(string $type, string $ref): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_accounts WHERE beneficiary_type = :beneficiary_type AND beneficiary_ref = :beneficiary_ref LIMIT 1');
        $stmt->execute(['beneficiary_type' => $type, 'beneficiary_ref' => $ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizePayoutAccount($row) : null;
    }

    /** @return array<string, mixed> */
    private function requirePayoutAccount(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_accounts WHERE id = :id OR beneficiary_ref = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Payout account not found.');
        }
        return $this->normalizePayoutAccount($row);
    }

    /** @return array<string, mixed> */
    private function requirePayoutRun(string $idOrCode): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_payout_runs WHERE id = :id OR run_code = :id LIMIT 1');
        $stmt->execute(['id' => $idOrCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Payout run not found.');
        }
        return $this->normalizePayoutRun($row);
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function sum(string $table, string $column, ?string $where = null): int
    {
        $sql = 'SELECT COALESCE(SUM(' . $column . '), 0) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function audit(string $action, string $subjectType, ?string $subjectId, string $message, array $metadata, ?string $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_revenue_audit_log (id, action, subject_type, subject_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :subject_type, :subject_id, :message, :metadata_json, :created_by, :created_at)');
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

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeFeePolicy(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'fee_key' => (string) ($row['fee_key'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'scope' => (string) ($row['scope'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'percentage_bps' => (int) ($row['percentage_bps'] ?? 0),
            'percentage' => round(((int) ($row['percentage_bps'] ?? 0)) / 100, 2),
            'fixed_fee_cents' => (int) ($row['fixed_fee_cents'] ?? 0),
            'min_fee_cents' => (int) ($row['min_fee_cents'] ?? 0),
            'max_fee_cents' => isset($row['max_fee_cents']) ? (int) $row['max_fee_cents'] : null,
            'applies_to' => (string) ($row['applies_to'] ?? ''),
            'notes' => $row['notes'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeCreditAccount(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'owner_type' => (string) ($row['owner_type'] ?? ''),
            'owner_ref' => (string) ($row['owner_ref'] ?? ''),
            'display_name' => (string) ($row['display_name'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'balance_credits' => (int) ($row['balance_credits'] ?? 0),
            'lifetime_earned_credits' => (int) ($row['lifetime_earned_credits'] ?? 0),
            'lifetime_spent_credits' => (int) ($row['lifetime_spent_credits'] ?? 0),
            'notes' => $row['notes'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeCreditTransaction(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'account_id' => (string) ($row['account_id'] ?? ''),
            'owner_type' => (string) ($row['owner_type'] ?? ''),
            'owner_ref' => (string) ($row['owner_ref'] ?? ''),
            'display_name' => (string) ($row['display_name'] ?? ''),
            'transaction_type' => (string) ($row['transaction_type'] ?? ''),
            'amount_credits' => (int) ($row['amount_credits'] ?? 0),
            'balance_after_credits' => (int) ($row['balance_after_credits'] ?? 0),
            'source_type' => (string) ($row['source_type'] ?? ''),
            'source_id' => $row['source_id'] ?? null,
            'description' => (string) ($row['description'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePayoutAccount(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'beneficiary_type' => (string) ($row['beneficiary_type'] ?? ''),
            'beneficiary_ref' => (string) ($row['beneficiary_ref'] ?? ''),
            'display_name' => (string) ($row['display_name'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'payout_method' => (string) ($row['payout_method'] ?? ''),
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'hold_days' => (int) ($row['hold_days'] ?? 0),
            'minimum_payout_cents' => (int) ($row['minimum_payout_cents'] ?? 0),
            'notes' => $row['notes'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePayoutRun(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'run_code' => (string) ($row['run_code'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'period_start' => $row['period_start'] ?? null,
            'period_end' => $row['period_end'] ?? null,
            'item_count' => (int) ($row['item_count'] ?? 0),
            'gross_amount_cents' => (int) ($row['gross_amount_cents'] ?? 0),
            'platform_fee_cents' => (int) ($row['platform_fee_cents'] ?? 0),
            'payout_amount_cents' => (int) ($row['payout_amount_cents'] ?? 0),
            'credits_amount' => (int) ($row['credits_amount'] ?? 0),
            'notes' => $row['notes'] ?? null,
            'evaluated_at' => $row['evaluated_at'] ?? null,
            'approved_at' => $row['approved_at'] ?? null,
            'paid_at' => $row['paid_at'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizePayoutItem(array $row): array
    {
        $evidence = json_decode((string) ($row['evidence_json'] ?? '{}'), true);
        return [
            'id' => (string) ($row['id'] ?? ''),
            'payout_run_id' => (string) ($row['payout_run_id'] ?? ''),
            'payout_account_id' => $row['payout_account_id'] ?? null,
            'beneficiary_type' => (string) ($row['beneficiary_type'] ?? ''),
            'beneficiary_ref' => (string) ($row['beneficiary_ref'] ?? ''),
            'display_name' => (string) ($row['display_name'] ?? ''),
            'source_type' => (string) ($row['source_type'] ?? ''),
            'source_id' => (string) ($row['source_id'] ?? ''),
            'gross_amount_cents' => (int) ($row['gross_amount_cents'] ?? 0),
            'platform_fee_cents' => (int) ($row['platform_fee_cents'] ?? 0),
            'payout_amount_cents' => (int) ($row['payout_amount_cents'] ?? 0),
            'credits_earned' => (int) ($row['credits_earned'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'evidence' => is_array($evidence) ? $evidence : [],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeAudit(array $row): array
    {
        $metadata = json_decode((string) ($row['metadata_json'] ?? '{}'), true);
        return [
            'id' => (string) ($row['id'] ?? ''),
            'action' => (string) ($row['action'] ?? ''),
            'subject_type' => (string) ($row['subject_type'] ?? ''),
            'subject_id' => $row['subject_id'] ?? null,
            'message' => (string) ($row['message'] ?? ''),
            'metadata' => is_array($metadata) ? $metadata : [],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
