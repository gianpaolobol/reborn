CREATE TABLE IF NOT EXISTS platform_feature_flags (
    id TEXT PRIMARY KEY,
    flag_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    scope TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'disabled',
    default_state INTEGER NOT NULL DEFAULT 0,
    rollout_percentage INTEGER NOT NULL DEFAULT 0,
    owner_role TEXT NOT NULL DEFAULT 'admin',
    description TEXT NULL,
    dependencies_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_feature_flags_status
ON platform_feature_flags(status, scope);

CREATE TABLE IF NOT EXISTS platform_releases (
    id TEXT PRIMARY KEY,
    release_code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    version TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    target_environment TEXT NOT NULL DEFAULT 'local_pilot',
    release_type TEXT NOT NULL DEFAULT 'pilot',
    scope TEXT NOT NULL DEFAULT 'platform',
    risk_level TEXT NOT NULL DEFAULT 'medium',
    notes TEXT NULL,
    scheduled_at TEXT NULL,
    approved_by TEXT NULL,
    approved_at TEXT NULL,
    deployed_at TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_releases_status
ON platform_releases(status, target_environment, created_at);

CREATE TABLE IF NOT EXISTS platform_release_gates (
    id TEXT PRIMARY KEY,
    release_id TEXT NOT NULL,
    gate_key TEXT NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    required INTEGER NOT NULL DEFAULT 1,
    evidence_json TEXT NOT NULL DEFAULT '{}',
    evaluated_at TEXT NULL,
    evaluated_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (release_id) REFERENCES platform_releases(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_platform_release_gates_unique
ON platform_release_gates(release_id, gate_key);

CREATE INDEX IF NOT EXISTS idx_platform_release_gates_status
ON platform_release_gates(status, required);

CREATE TABLE IF NOT EXISTS platform_release_decisions (
    id TEXT PRIMARY KEY,
    release_id TEXT NOT NULL,
    decision TEXT NOT NULL,
    rationale TEXT NOT NULL,
    decided_by TEXT NULL,
    decided_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (release_id) REFERENCES platform_releases(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_release_decisions_release
ON platform_release_decisions(release_id, decided_at);

CREATE TABLE IF NOT EXISTS platform_pilot_cohorts (
    id TEXT PRIMARY KEY,
    cohort_code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    target_persona TEXT NOT NULL,
    size_limit INTEGER NOT NULL DEFAULT 10,
    admission_criteria_json TEXT NOT NULL DEFAULT '[]',
    exit_criteria_json TEXT NOT NULL DEFAULT '[]',
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_pilot_cohorts_status
ON platform_pilot_cohorts(status, target_persona);

CREATE TABLE IF NOT EXISTS platform_pilot_participants (
    id TEXT PRIMARY KEY,
    cohort_id TEXT NOT NULL,
    display_name TEXT NOT NULL,
    email TEXT NOT NULL,
    role TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'invited',
    source TEXT NOT NULL DEFAULT 'admin_console',
    consent_status TEXT NOT NULL DEFAULT 'pending',
    onboarding_state TEXT NOT NULL DEFAULT 'not_started',
    notes TEXT NULL,
    joined_at TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (cohort_id) REFERENCES platform_pilot_cohorts(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_pilot_participants_cohort_status
ON platform_pilot_participants(cohort_id, status);

CREATE INDEX IF NOT EXISTS idx_platform_pilot_participants_email
ON platform_pilot_participants(email);

INSERT OR IGNORE INTO platform_feature_flags (id, flag_key, name, scope, status, default_state, rollout_percentage, owner_role, description, dependencies_json, created_at, updated_at)
VALUES
('flag-live-repair-intake', 'live_repair_intake', 'Live repair intake', 'repair_journey', 'enabled', 1, 100, 'admin', 'Allows authenticated users to create real repair cases in the local/pilot API.', '["identity_access", "repair_case_ownership", "privacy_notice"]', datetime('now'), datetime('now')),
('flag-ai-recognition-mock', 'ai_recognition_mock', 'Mock AI recognition', 'ai', 'enabled', 1, 100, 'admin', 'Uses the local mock recognition engine for pilot-safe diagnosis demonstrations.', '["repair_uploads", "knowledge_engine"]', datetime('now'), datetime('now')),
('flag-real-ai-recognition', 'real_ai_recognition', 'Real AI recognition provider', 'ai', 'disabled', 0, 0, 'admin', 'Reserved for future external AI provider integration after privacy/security review.', '["provider_dpa", "privacy_review", "cost_controls", "human_review"]', datetime('now'), datetime('now')),
('flag-ai-3d-generation', 'ai_3d_generation', 'AI 3D model generation', 'maker_economy', 'disabled', 0, 0, 'admin', 'Reserved for image/CAD to model generation once AI provider, costs and rights policies are approved.', '["privacy_review", "ip_rights_policy", "model_quality_review"]', datetime('now'), datetime('now')),
('flag-mock-payments', 'mock_payments', 'Mock payment intents', 'marketplace', 'enabled', 1, 100, 'admin', 'Keeps checkout demonstrable with local mock payment authorization only.', '["repair_orders"]', datetime('now'), datetime('now')),
('flag-real-payments', 'real_payments', 'Real payment provider integration', 'marketplace', 'disabled', 0, 0, 'admin', 'Reserved for Stripe/PayPal or equivalent production payment provider.', '["legal_terms", "webhook_security", "payout_model", "refund_policy"]', datetime('now'), datetime('now')),
('flag-provider-onboarding', 'provider_onboarding', 'Provider onboarding pilot', 'provider_network', 'beta', 0, 25, 'admin', 'Allows controlled pilot onboarding of selected providers only.', '["provider_privacy_notice", "quality_policy", "support_workflow"]', datetime('now'), datetime('now')),
('flag-maker-economy', 'maker_economy', 'Maker uploads, royalty and repair credits', 'maker_economy', 'disabled', 0, 0, 'admin', 'Reserved for the future maker economy layer: uploads, credits, royalties and bounties.', '["ip_rights_policy", "royalty_ledger", "tax_review", "anti_abuse"]', datetime('now'), datetime('now')),
('flag-public-status-page', 'public_status_page', 'Public status page', 'platform_operations', 'beta', 0, 50, 'admin', 'Exposes sanitized platform status for pilot operations without private data.', '["incident_policy", "privacy_review"]', datetime('now'), datetime('now')),
('flag-dsr-json-export', 'dsr_json_export', 'Local DSR JSON export', 'privacy', 'enabled', 1, 100, 'admin', 'Allows local JSON export generation for data subject request workflow testing.', '["privacy_governance"]', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_releases (id, release_code, title, version, status, target_environment, release_type, scope, risk_level, notes, scheduled_at, created_by, created_at, updated_at)
VALUES
('release-local-beta-readiness-v1', 'REL-LOCAL-BETA-READINESS-001', 'Local beta readiness baseline', '0.26.0', 'draft', 'local_pilot', 'beta_readiness', 'platform', 'medium', 'Tracks whether the end-to-end Repair Journey MVP is ready for a controlled local demo/beta.', datetime('now', '+7 days'), NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_pilot_cohorts (id, cohort_code, name, status, target_persona, size_limit, admission_criteria_json, exit_criteria_json, notes, created_by, created_at, updated_at)
VALUES
('cohort-bologna-repair-users', 'PILOT-BOLOGNA-REPAIR-USERS', 'Bologna repair user pilot', 'draft', 'repair_user', 10, '["uses non-sensitive demo repair objects", "accepts pilot privacy notice", "can provide repair outcome feedback"]', '["at least 5 repair journeys completed", "no unresolved critical incidents", "feedback collected for diagnosis and provider matching"]', 'Controlled early cohort for validating user-side Repair Journey clarity.', NULL, datetime('now'), datetime('now')),
('cohort-maker-provider-network', 'PILOT-MAKER-PROVIDER-NETWORK', 'Maker and provider fulfilment pilot', 'draft', 'maker_provider', 8, '["has 3D printing or repair fulfilment capacity", "accepts provider data/privacy notice", "can respond to quote requests during pilot"]', '["at least 3 fulfilment workflows completed", "quality score and trust review generated", "provider governance workflow reviewed"]', 'Controlled early cohort for testing provider matching, quotes, fulfilment and trust scoring.', NULL, datetime('now'), datetime('now')),
('cohort-enterprise-design-partners', 'PILOT-ENTERPRISE-DESIGN-PARTNERS', 'Enterprise design partner pilot', 'draft', 'enterprise', 3, '["clear repair use case", "no sensitive production data", "agrees to roadmap feedback sessions"]', '["enterprise value hypothesis validated", "support and privacy requirements documented", "integration needs classified"]', 'Small discovery cohort for enterprise Repair Intelligence workflows.', NULL, datetime('now'), datetime('now'));
