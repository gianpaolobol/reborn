CREATE TABLE IF NOT EXISTS platform_maker_profiles (
    id TEXT PRIMARY KEY,
    maker_ref TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'onboarding',
    trust_tier TEXT NOT NULL DEFAULT 'new',
    specialty_tags_json TEXT NOT NULL DEFAULT '[]',
    credit_account_id TEXT NULL,
    payout_account_id TEXT NULL,
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (credit_account_id) REFERENCES platform_credit_accounts(id),
    FOREIGN KEY (payout_account_id) REFERENCES platform_payout_accounts(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_maker_profiles_status
ON platform_maker_profiles(status, trust_tier);

CREATE TABLE IF NOT EXISTS platform_model_assets (
    id TEXT PRIMARY KEY,
    maker_profile_id TEXT NOT NULL,
    title TEXT NOT NULL,
    object_category TEXT NOT NULL,
    repair_use_case TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'submitted',
    license_key TEXT NOT NULL DEFAULT 'repair_credit_pilot',
    file_kind TEXT NOT NULL DEFAULT 'stl',
    quality_score INTEGER NOT NULL DEFAULT 0,
    safety_notes TEXT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    submitted_by TEXT NULL,
    reviewed_by TEXT NULL,
    reviewed_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (maker_profile_id) REFERENCES platform_maker_profiles(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_model_assets_status
ON platform_model_assets(status, object_category, created_at);

CREATE TABLE IF NOT EXISTS platform_model_licenses (
    id TEXT PRIMARY KEY,
    license_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    allowed_use TEXT NOT NULL,
    requires_attribution INTEGER NOT NULL DEFAULT 1,
    commercial_use_allowed INTEGER NOT NULL DEFAULT 0,
    royalty_credits_per_download INTEGER NOT NULL DEFAULT 0,
    royalty_cents_per_download INTEGER NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_model_licenses_status
ON platform_model_licenses(status, license_key);

CREATE TABLE IF NOT EXISTS platform_model_downloads (
    id TEXT PRIMARY KEY,
    model_asset_id TEXT NOT NULL,
    license_id TEXT NULL,
    downloader_type TEXT NOT NULL DEFAULT 'repair_user',
    downloader_ref TEXT NOT NULL,
    purpose TEXT NOT NULL DEFAULT 'repair_attempt',
    status TEXT NOT NULL DEFAULT 'recorded',
    credits_charged INTEGER NOT NULL DEFAULT 0,
    royalty_credits INTEGER NOT NULL DEFAULT 0,
    recorded_by TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (model_asset_id) REFERENCES platform_model_assets(id),
    FOREIGN KEY (license_id) REFERENCES platform_model_licenses(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_model_downloads_asset
ON platform_model_downloads(model_asset_id, created_at);

CREATE TABLE IF NOT EXISTS platform_model_royalty_events (
    id TEXT PRIMARY KEY,
    model_asset_id TEXT NOT NULL,
    maker_profile_id TEXT NOT NULL,
    download_id TEXT NULL,
    credit_transaction_id TEXT NULL,
    royalty_type TEXT NOT NULL DEFAULT 'download_credit',
    credits_awarded INTEGER NOT NULL DEFAULT 0,
    amount_cents INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'posted',
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (model_asset_id) REFERENCES platform_model_assets(id),
    FOREIGN KEY (maker_profile_id) REFERENCES platform_maker_profiles(id),
    FOREIGN KEY (download_id) REFERENCES platform_model_downloads(id),
    FOREIGN KEY (credit_transaction_id) REFERENCES platform_credit_transactions(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_model_royalty_events_maker
ON platform_model_royalty_events(maker_profile_id, status, created_at);

CREATE TABLE IF NOT EXISTS platform_repair_bounties (
    id TEXT PRIMARY KEY,
    bounty_code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    object_category TEXT NOT NULL,
    problem_statement TEXT NOT NULL,
    reward_credits INTEGER NOT NULL DEFAULT 0,
    reward_cents INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'normal',
    source_type TEXT NOT NULL DEFAULT 'ops',
    source_ref TEXT NULL,
    due_at TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_repair_bounties_status
ON platform_repair_bounties(status, priority, created_at);

CREATE TABLE IF NOT EXISTS platform_bounty_submissions (
    id TEXT PRIMARY KEY,
    bounty_id TEXT NOT NULL,
    maker_profile_id TEXT NOT NULL,
    model_asset_id TEXT NULL,
    status TEXT NOT NULL DEFAULT 'submitted',
    submission_notes TEXT NOT NULL,
    review_notes TEXT NULL,
    awarded_credits INTEGER NOT NULL DEFAULT 0,
    awarded_cents INTEGER NOT NULL DEFAULT 0,
    submitted_by TEXT NULL,
    reviewed_by TEXT NULL,
    reviewed_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (bounty_id) REFERENCES platform_repair_bounties(id),
    FOREIGN KEY (maker_profile_id) REFERENCES platform_maker_profiles(id),
    FOREIGN KEY (model_asset_id) REFERENCES platform_model_assets(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_bounty_submissions_bounty
ON platform_bounty_submissions(bounty_id, status, created_at);

CREATE TABLE IF NOT EXISTS platform_maker_economy_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT NULL,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_maker_economy_audit_log_subject
ON platform_maker_economy_audit_log(subject_type, subject_id, created_at);

INSERT OR IGNORE INTO platform_model_licenses (id, license_key, name, status, allowed_use, requires_attribution, commercial_use_allowed, royalty_credits_per_download, royalty_cents_per_download, notes, created_at, updated_at)
VALUES
('model-license-repair-credit-pilot', 'repair_credit_pilot', 'Repair Credit Pilot License', 'active', 'Download and use only for repair attempts inside the Re-born pilot flow.', 1, 0, 8, 0, 'Credits are awarded in the local ledger only; no cash royalty is paid by this license.', datetime('now'), datetime('now')),
('model-license-maker-commercial-review', 'maker_commercial_review', 'Commercial Use Requires Review', 'draft', 'Commercial manufacturing requires explicit Re-born and maker approval.', 1, 0, 12, 0, 'Draft license for future paid provider use of maker CAD models.', datetime('now'), datetime('now')),
('model-license-enterprise-private-repair', 'enterprise_private_repair', 'Enterprise Private Repair License', 'draft', 'Private enterprise repair use under signed partner agreement only.', 1, 0, 0, 0, 'Draft placeholder; legal and enterprise API work remain future scope.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_maker_profiles (id, maker_ref, display_name, status, trust_tier, specialty_tags_json, credit_account_id, payout_account_id, notes, created_by, created_at, updated_at)
VALUES
('maker-profile-demo-001', 'maker-demo-001', 'Maker Demo 001', 'active', 'verified', '["small_appliances","wearables","reverse_engineering"]', 'credit-account-maker-demo-001', 'payout-account-maker-demo-001', 'Demo maker profile connected to the Step 28 credit account.', NULL, datetime('now'), datetime('now')),
('maker-profile-demo-002', 'maker-demo-002', 'Maker Demo 002', 'active', 'emerging', '["home_repair","handles","functional_parts"]', 'credit-account-maker-demo-002', NULL, 'Demo maker profile for bounty and model licensing workflows.', NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_model_assets (id, maker_profile_id, title, object_category, repair_use_case, status, license_key, file_kind, quality_score, safety_notes, metadata_json, submitted_by, reviewed_by, reviewed_at, created_at, updated_at)
VALUES
('model-asset-garmin-strap-connector', 'maker-profile-demo-001', 'Garmin strap connector repair insert', 'wearable', 'Replace a cracked strap connector with a printable repair insert.', 'approved', 'repair_credit_pilot', 'stl', 86, 'Pilot sample only; dimensional fit must be verified before field use.', '{"source":"seed","estimated_print_time_minutes":42,"material":"PETG"}', NULL, NULL, datetime('now'), datetime('now'), datetime('now')),
('model-asset-washing-machine-handle', 'maker-profile-demo-002', 'Washing machine handle replacement concept', 'appliance', 'Replace a broken plastic handle where OEM part is unavailable.', 'in_review', 'repair_credit_pilot', 'step', 72, 'Needs load testing and provider review before approval.', '{"source":"seed","material":"ASA","requires_support":true}', NULL, NULL, NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_repair_bounties (id, bounty_code, title, object_category, problem_statement, reward_credits, reward_cents, status, priority, source_type, source_ref, due_at, created_by, created_at, updated_at)
VALUES
('repair-bounty-laundry-knob-001', 'BOUNTY-LAUNDRY-KNOB-001', 'Laundry machine selector knob', 'appliance', 'Create a printable replacement knob for common washing machine selectors where the shaft is intact but the grip is broken.', 60, 0, 'open', 'normal', 'ops', 'pilot-bounty-seed', NULL, NULL, datetime('now'), datetime('now')),
('repair-bounty-sunglasses-hinge-001', 'BOUNTY-SUNGLASSES-HINGE-001', 'Sunglasses hinge spacer', 'eyewear', 'Design a small hinge spacer that can restore fit for a common eyewear repair case.', 45, 0, 'open', 'high', 'ops', 'pilot-bounty-seed', NULL, NULL, datetime('now'), datetime('now'));
