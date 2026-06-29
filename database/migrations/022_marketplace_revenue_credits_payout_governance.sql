CREATE TABLE IF NOT EXISTS platform_marketplace_fee_policies (
    id TEXT PRIMARY KEY,
    fee_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    scope TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    currency TEXT NOT NULL DEFAULT 'EUR',
    percentage_bps INTEGER NOT NULL DEFAULT 0,
    fixed_fee_cents INTEGER NOT NULL DEFAULT 0,
    min_fee_cents INTEGER NOT NULL DEFAULT 0,
    max_fee_cents INTEGER NULL,
    applies_to TEXT NOT NULL DEFAULT 'repair_order',
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_marketplace_fee_policies_status
ON platform_marketplace_fee_policies(status, scope, applies_to);

CREATE TABLE IF NOT EXISTS platform_credit_accounts (
    id TEXT PRIMARY KEY,
    owner_type TEXT NOT NULL,
    owner_ref TEXT NOT NULL,
    display_name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    balance_credits INTEGER NOT NULL DEFAULT 0,
    lifetime_earned_credits INTEGER NOT NULL DEFAULT 0,
    lifetime_spent_credits INTEGER NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_platform_credit_accounts_owner
ON platform_credit_accounts(owner_type, owner_ref);

CREATE INDEX IF NOT EXISTS idx_platform_credit_accounts_status
ON platform_credit_accounts(status, owner_type);

CREATE TABLE IF NOT EXISTS platform_credit_transactions (
    id TEXT PRIMARY KEY,
    account_id TEXT NOT NULL,
    transaction_type TEXT NOT NULL,
    amount_credits INTEGER NOT NULL,
    balance_after_credits INTEGER NOT NULL,
    source_type TEXT NOT NULL DEFAULT 'manual',
    source_id TEXT NULL,
    description TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'posted',
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (account_id) REFERENCES platform_credit_accounts(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_credit_transactions_account
ON platform_credit_transactions(account_id, created_at);

CREATE INDEX IF NOT EXISTS idx_platform_credit_transactions_source
ON platform_credit_transactions(source_type, source_id);

CREATE TABLE IF NOT EXISTS platform_payout_accounts (
    id TEXT PRIMARY KEY,
    beneficiary_type TEXT NOT NULL,
    beneficiary_ref TEXT NOT NULL,
    display_name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    payout_method TEXT NOT NULL DEFAULT 'mock_manual',
    currency TEXT NOT NULL DEFAULT 'EUR',
    hold_days INTEGER NOT NULL DEFAULT 7,
    minimum_payout_cents INTEGER NOT NULL DEFAULT 2500,
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_platform_payout_accounts_beneficiary
ON platform_payout_accounts(beneficiary_type, beneficiary_ref);

CREATE INDEX IF NOT EXISTS idx_platform_payout_accounts_status
ON platform_payout_accounts(status, beneficiary_type);

CREATE TABLE IF NOT EXISTS platform_payout_runs (
    id TEXT PRIMARY KEY,
    run_code TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    currency TEXT NOT NULL DEFAULT 'EUR',
    period_start TEXT NULL,
    period_end TEXT NULL,
    item_count INTEGER NOT NULL DEFAULT 0,
    gross_amount_cents INTEGER NOT NULL DEFAULT 0,
    platform_fee_cents INTEGER NOT NULL DEFAULT 0,
    payout_amount_cents INTEGER NOT NULL DEFAULT 0,
    credits_amount INTEGER NOT NULL DEFAULT 0,
    notes TEXT NULL,
    evaluated_by TEXT NULL,
    evaluated_at TEXT NULL,
    approved_by TEXT NULL,
    approved_at TEXT NULL,
    paid_by TEXT NULL,
    paid_at TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_payout_runs_status
ON platform_payout_runs(status, created_at);

CREATE TABLE IF NOT EXISTS platform_payout_items (
    id TEXT PRIMARY KEY,
    payout_run_id TEXT NOT NULL,
    payout_account_id TEXT NULL,
    beneficiary_type TEXT NOT NULL,
    beneficiary_ref TEXT NOT NULL,
    display_name TEXT NOT NULL,
    source_type TEXT NOT NULL,
    source_id TEXT NOT NULL,
    gross_amount_cents INTEGER NOT NULL DEFAULT 0,
    platform_fee_cents INTEGER NOT NULL DEFAULT 0,
    payout_amount_cents INTEGER NOT NULL DEFAULT 0,
    credits_earned INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'evaluated',
    evidence_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    FOREIGN KEY (payout_run_id) REFERENCES platform_payout_runs(id),
    FOREIGN KEY (payout_account_id) REFERENCES platform_payout_accounts(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_platform_payout_items_unique_source
ON platform_payout_items(source_type, source_id);

CREATE INDEX IF NOT EXISTS idx_platform_payout_items_run
ON platform_payout_items(payout_run_id, status);

CREATE TABLE IF NOT EXISTS platform_revenue_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT NULL,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_revenue_audit_log_subject
ON platform_revenue_audit_log(subject_type, subject_id, created_at);

INSERT OR IGNORE INTO platform_marketplace_fee_policies (id, fee_key, name, scope, status, currency, percentage_bps, fixed_fee_cents, min_fee_cents, max_fee_cents, applies_to, notes, created_at, updated_at)
VALUES
('fee-policy-repair-order-pilot', 'repair_order_pilot_fee', 'Repair order pilot platform fee', 'marketplace', 'active', 'EUR', 1200, 0, 250, NULL, 'repair_order', 'Mirrors the current mock quote engine platform fee: 12% with minimum local pilot fee.', datetime('now'), datetime('now')),
('fee-policy-provider-payout-hold', 'provider_payout_hold_policy', 'Provider payout hold policy', 'provider_network', 'draft', 'EUR', 0, 0, 0, NULL, 'payout', 'Documents hold days and manual payout governance; no real payment rail is enabled.', datetime('now'), datetime('now')),
('fee-policy-maker-royalty-pilot', 'maker_royalty_pilot', 'Maker royalty pilot policy', 'maker_economy', 'draft', 'EUR', 1000, 0, 0, NULL, 'model_download', 'Placeholder for future maker royalties and repair credits. Not production payable yet.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_credit_accounts (id, owner_type, owner_ref, display_name, status, balance_credits, lifetime_earned_credits, lifetime_spent_credits, notes, created_by, created_at, updated_at)
VALUES
('credit-account-maker-demo-001', 'maker', 'maker-demo-001', 'Maker Demo 001', 'active', 120, 120, 0, 'Seed maker credit account for repair model contribution demo.', NULL, datetime('now'), datetime('now')),
('credit-account-maker-demo-002', 'maker', 'maker-demo-002', 'Maker Demo 002', 'active', 80, 80, 0, 'Seed maker credit account for future royalty and credit workflows.', NULL, datetime('now'), datetime('now')),
('credit-account-provider-bologna', 'provider', 'provider-bologna-lab', 'Bologna Repair Lab', 'active', 40, 40, 0, 'Seed provider credit account for pilot incentives and manual adjustments.', NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_credit_transactions (id, account_id, transaction_type, amount_credits, balance_after_credits, source_type, source_id, description, status, created_by, created_at)
VALUES
('credit-txn-maker-demo-001-seed', 'credit-account-maker-demo-001', 'grant', 120, 120, 'seed', 'cad-demo-garmin-strap-connector', 'Seed credits for verified Garmin strap connector model contribution.', 'posted', NULL, datetime('now')),
('credit-txn-maker-demo-002-seed', 'credit-account-maker-demo-002', 'grant', 80, 80, 'seed', 'cad-demo-washing-machine-handle', 'Seed credits for washing machine handle prototype contribution.', 'posted', NULL, datetime('now')),
('credit-txn-provider-bologna-seed', 'credit-account-provider-bologna', 'grant', 40, 40, 'seed', 'provider-bologna-lab', 'Seed pilot incentive credits for Bologna Repair Lab.', 'posted', NULL, datetime('now'));

INSERT OR IGNORE INTO platform_payout_accounts (id, beneficiary_type, beneficiary_ref, display_name, status, payout_method, currency, hold_days, minimum_payout_cents, notes, created_by, created_at, updated_at)
VALUES
('payout-account-provider-bologna', 'provider', 'provider-bologna-lab', 'Bologna Repair Lab', 'active', 'mock_manual', 'EUR', 7, 2500, 'Manual mock payout account for local provider pilot. No real payout rail enabled.', NULL, datetime('now'), datetime('now')),
('payout-account-provider-milan', 'provider', 'provider-milan-maker', 'Milano Distributed Manufacturing', 'pending', 'mock_manual', 'EUR', 7, 2500, 'Pending payout account; requires partner onboarding and payout policy approval.', NULL, datetime('now'), datetime('now')),
('payout-account-maker-demo-001', 'maker', 'maker-demo-001', 'Maker Demo 001', 'pending', 'mock_manual', 'EUR', 14, 1500, 'Pending maker payout account for future royalty workflow.', NULL, datetime('now'), datetime('now'));
