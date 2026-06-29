CREATE TABLE IF NOT EXISTS platform_partner_accounts (
    id TEXT PRIMARY KEY,
    partner_code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    partner_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'prospect',
    tier TEXT NOT NULL DEFAULT 'pilot',
    country TEXT NOT NULL DEFAULT 'IT',
    contact_name TEXT NULL,
    contact_email TEXT NULL,
    readiness_score INTEGER NOT NULL DEFAULT 0,
    risk_level TEXT NOT NULL DEFAULT 'medium',
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_partner_accounts_status
ON platform_partner_accounts(status, partner_type, tier);

CREATE TABLE IF NOT EXISTS platform_partner_onboarding_tasks (
    id TEXT PRIMARY KEY,
    partner_id TEXT NOT NULL,
    task_key TEXT NOT NULL,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    required INTEGER NOT NULL DEFAULT 1,
    evidence TEXT NULL,
    due_at TEXT NULL,
    completed_at TEXT NULL,
    completed_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (partner_id) REFERENCES platform_partner_accounts(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_platform_partner_tasks_unique
ON platform_partner_onboarding_tasks(partner_id, task_key);

CREATE INDEX IF NOT EXISTS idx_platform_partner_tasks_status
ON platform_partner_onboarding_tasks(partner_id, status, required);

CREATE TABLE IF NOT EXISTS platform_partner_agreements (
    id TEXT PRIMARY KEY,
    partner_id TEXT NOT NULL,
    agreement_type TEXT NOT NULL,
    title TEXT NOT NULL,
    version TEXT NOT NULL DEFAULT 'v1',
    status TEXT NOT NULL DEFAULT 'draft',
    owner_role TEXT NOT NULL DEFAULT 'admin',
    signed_at TEXT NULL,
    expires_at TEXT NULL,
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (partner_id) REFERENCES platform_partner_accounts(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_partner_agreements_status
ON platform_partner_agreements(partner_id, agreement_type, status);

CREATE TABLE IF NOT EXISTS platform_partner_integrations (
    id TEXT PRIMARY KEY,
    partner_id TEXT NOT NULL,
    integration_type TEXT NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'planned',
    environment TEXT NOT NULL DEFAULT 'pilot',
    scopes_json TEXT NOT NULL DEFAULT '[]',
    last_checked_at TEXT NULL,
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (partner_id) REFERENCES platform_partner_accounts(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_partner_integrations_status
ON platform_partner_integrations(partner_id, status, integration_type);

CREATE TABLE IF NOT EXISTS platform_partner_readiness_reviews (
    id TEXT PRIMARY KEY,
    partner_id TEXT NOT NULL,
    status TEXT NOT NULL,
    readiness_score INTEGER NOT NULL DEFAULT 0,
    gates_json TEXT NOT NULL DEFAULT '[]',
    notes TEXT NULL,
    reviewed_by TEXT NULL,
    reviewed_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (partner_id) REFERENCES platform_partner_accounts(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_partner_readiness_reviews_partner
ON platform_partner_readiness_reviews(partner_id, reviewed_at);

INSERT OR IGNORE INTO platform_partner_accounts (id, partner_code, name, partner_type, status, tier, country, contact_name, contact_email, readiness_score, risk_level, notes, created_by, created_at, updated_at)
VALUES
('partner-bologna-maker-lab', 'PARTNER-BOLOGNA-MAKER-LAB', 'Bologna Maker Lab', 'provider', 'onboarding', 'pilot', 'IT', 'Provider Lead', 'provider@reborn.local', 45, 'medium', 'Pilot provider for local repair fulfilment and 3D printing workflows.', NULL, datetime('now'), datetime('now')),
('partner-enterprise-design-partner', 'PARTNER-ENTERPRISE-DESIGN-001', 'Enterprise Design Partner', 'enterprise', 'prospect', 'strategic', 'IT', 'Innovation Lead', 'enterprise@reborn.local', 20, 'high', 'Discovery partner for enterprise Repair Intelligence workflows. No production data allowed during pilot.', NULL, datetime('now'), datetime('now')),
('partner-community-maker', 'PARTNER-COMMUNITY-MAKER-001', 'Community Maker Pilot', 'maker', 'onboarding', 'pilot', 'IT', 'Maker Lead', 'maker@reborn.local', 35, 'medium', 'Community maker account for testing model contribution, bounty and fulfilment governance later.', NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_partner_onboarding_tasks (id, partner_id, task_key, name, category, status, required, evidence, due_at, created_at, updated_at)
VALUES
('task-bologna-provider-profile', 'partner-bologna-maker-lab', 'profile_verified', 'Verify provider profile and operating area', 'profile', 'completed', 1, 'Seed evidence: demo provider profile available.', datetime('now', '+7 days'), datetime('now'), datetime('now')),
('task-bologna-provider-privacy', 'partner-bologna-maker-lab', 'privacy_notice_accepted', 'Accept provider privacy notice and data processing scope', 'privacy', 'pending', 1, NULL, datetime('now', '+7 days'), datetime('now'), datetime('now')),
('task-bologna-provider-quality', 'partner-bologna-maker-lab', 'quality_policy_acknowledged', 'Acknowledge provider quality and evidence policy', 'quality', 'pending', 1, NULL, datetime('now', '+10 days'), datetime('now'), datetime('now')),
('task-bologna-provider-sla', 'partner-bologna-maker-lab', 'sla_contact_ready', 'Confirm SLA response contact and escalation path', 'operations', 'pending', 1, NULL, datetime('now', '+10 days'), datetime('now'), datetime('now')),
('task-enterprise-use-case', 'partner-enterprise-design-partner', 'pilot_use_case_documented', 'Document pilot repair intelligence use case', 'discovery', 'pending', 1, NULL, datetime('now', '+14 days'), datetime('now'), datetime('now')),
('task-enterprise-data-boundary', 'partner-enterprise-design-partner', 'data_boundary_approved', 'Approve no-production-data pilot boundary', 'privacy', 'pending', 1, NULL, datetime('now', '+14 days'), datetime('now'), datetime('now')),
('task-community-maker-ip', 'partner-community-maker', 'ip_terms_acknowledged', 'Acknowledge IP, model upload and contribution terms draft', 'legal', 'pending', 1, NULL, datetime('now', '+14 days'), datetime('now'), datetime('now')),
('task-community-maker-quality', 'partner-community-maker', 'sample_print_reviewed', 'Review sample print quality evidence', 'quality', 'pending', 1, NULL, datetime('now', '+14 days'), datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_partner_agreements (id, partner_id, agreement_type, title, version, status, owner_role, notes, created_by, created_at, updated_at)
VALUES
('agreement-bologna-provider-terms', 'partner-bologna-maker-lab', 'provider_terms', 'Pilot provider participation terms', 'v1', 'draft', 'admin', 'Local pilot agreement placeholder; not a signed production contract.', NULL, datetime('now'), datetime('now')),
('agreement-enterprise-dpa', 'partner-enterprise-design-partner', 'data_processing', 'Pilot data processing boundary', 'v1', 'draft', 'admin', 'Defines that enterprise pilot must not include production personal or confidential data.', NULL, datetime('now'), datetime('now')),
('agreement-community-maker-ip', 'partner-community-maker', 'ip_terms', 'Maker contribution and IP terms draft', 'v1', 'draft', 'admin', 'Required before future maker economy, royalty and repair credit workflows.', NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_partner_integrations (id, partner_id, integration_type, name, status, environment, scopes_json, last_checked_at, notes, created_by, created_at, updated_at)
VALUES
('integration-bologna-manual-quote', 'partner-bologna-maker-lab', 'manual', 'Manual quote and fulfilment workflow', 'testing', 'local_pilot', '["quote_request", "fulfilment_status", "quality_evidence"]', datetime('now'), 'Pilot-safe manual workflow; no external API dependency.', NULL, datetime('now'), datetime('now')),
('integration-enterprise-feedback', 'partner-enterprise-design-partner', 'email', 'Enterprise feedback intake', 'planned', 'local_pilot', '["feedback", "use_case_notes"]', NULL, 'Placeholder for structured feedback, not production integration.', NULL, datetime('now'), datetime('now'));
