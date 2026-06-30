CREATE TABLE IF NOT EXISTS platform_public_pilot_demo_pages (
    id TEXT PRIMARY KEY,
    page_key TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    audience TEXT NOT NULL DEFAULT 'public',
    slug TEXT NOT NULL UNIQUE,
    headline TEXT NOT NULL DEFAULT '',
    cta_label TEXT NOT NULL DEFAULT '',
    cta_route TEXT NOT NULL DEFAULT '',
    summary TEXT NOT NULL DEFAULT '',
    requirements_json TEXT NOT NULL DEFAULT '[]',
    caveats_json TEXT NOT NULL DEFAULT '[]',
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_external_pilot_intake_submissions (
    id TEXT PRIMARY KEY,
    submission_code TEXT NOT NULL UNIQUE,
    stakeholder_type TEXT NOT NULL DEFAULT 'partner',
    status TEXT NOT NULL DEFAULT 'new',
    organization_name TEXT NOT NULL DEFAULT '',
    contact_name TEXT NOT NULL DEFAULT '',
    email TEXT NOT NULL DEFAULT '',
    country TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    capabilities_json TEXT NOT NULL DEFAULT '[]',
    repair_categories_json TEXT NOT NULL DEFAULT '[]',
    motivation TEXT NOT NULL DEFAULT '',
    pilot_fit_score INTEGER NOT NULL DEFAULT 0,
    risk_level TEXT NOT NULL DEFAULT 'medium',
    triage_notes TEXT NOT NULL DEFAULT '',
    source TEXT NOT NULL DEFAULT 'public_pilot_demo',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_by TEXT,
    reviewed_at TEXT
);

CREATE TABLE IF NOT EXISTS platform_real_world_validation_cases (
    id TEXT PRIMARY KEY,
    case_code TEXT NOT NULL UNIQUE,
    submission_id TEXT,
    status TEXT NOT NULL DEFAULT 'candidate',
    repair_category TEXT NOT NULL DEFAULT 'general',
    object_name TEXT NOT NULL DEFAULT '',
    problem_statement TEXT NOT NULL DEFAULT '',
    success_criteria_json TEXT NOT NULL DEFAULT '[]',
    evidence_json TEXT NOT NULL DEFAULT '[]',
    pilot_fit_score INTEGER NOT NULL DEFAULT 0,
    governance_risk TEXT NOT NULL DEFAULT 'medium',
    owner TEXT NOT NULL DEFAULT 'operations',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT,
    FOREIGN KEY (submission_id) REFERENCES platform_external_pilot_intake_submissions(id)
);

CREATE TABLE IF NOT EXISTS platform_pilot_stakeholder_lead_scores (
    id TEXT PRIMARY KEY,
    submission_id TEXT NOT NULL,
    score INTEGER NOT NULL DEFAULT 0,
    score_band TEXT NOT NULL DEFAULT 'medium',
    readiness_signal TEXT NOT NULL DEFAULT 'unknown',
    strategic_fit_signal TEXT NOT NULL DEFAULT 'unknown',
    risk_signal TEXT NOT NULL DEFAULT 'medium',
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    FOREIGN KEY (submission_id) REFERENCES platform_external_pilot_intake_submissions(id)
);

CREATE TABLE IF NOT EXISTS platform_public_pilot_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_public_pilot_demo_pages (id, page_key, title, status, audience, slug, headline, cta_label, cta_route, summary, requirements_json, caveats_json, metadata_json, created_at, updated_at)
VALUES
('public-pilot-page-overview', 'public_pilot_overview', 'Public Pilot Demo Overview', 'active', 'public', 'repair-intelligence-pilot', 'A controlled pilot path for partners, makers, providers and early repair users.', 'Apply for pilot', '#/public-pilot', 'Explains Re-born as a repair intelligence platform and routes external stakeholders into a governed pilot intake workflow.', '["Describe real repair capability or repair need","Accept pilot-only caveats","Provide consent for follow-up"]', '["AI, payments, logistics and warranty flows remain pilot/mock unless explicitly enabled","No public sustainability or success-rate claim is certified yet","Submissions are triaged before any beta commitment"]', '{"step":42,"surface":"public_demo"}', datetime('now'), datetime('now')),
('public-pilot-page-provider', 'provider_maker_intake', 'Provider and Maker Pilot Intake', 'active', 'provider_maker', 'provider-maker-intake', 'Bring repair capability into the Re-born pilot network.', 'Submit capability', '#/public-pilot', 'Collects provider, maker and 3D printing service interest with capability, geography and repair-category signals.', '["Declare capabilities and materials honestly","Accept routing and quality governance review","No automatic publication or payout approval"]', '["KYC/KYB, legal terms and payout rails are not approved by this intake","Routing matches are test evidence until operations review"]', '{"step":42,"surface":"intake"}', datetime('now'), datetime('now')),
('public-pilot-page-repair-case', 'real_world_case_validation', 'Real-World Repair Case Validation', 'active', 'repair_user', 'repair-case-validation', 'Turn external demo interest into validated repair cases.', 'Submit repair case', '#/public-pilot', 'Captures pilot candidate repair problems and converts them into reviewable real-world validation cases.', '["Describe the object and failure clearly","Define success criteria","Provide safe evidence/photos only when requested"]', '["No repair promise is created by submitting a case","Customer acceptance, warranty and fulfilment remain subject to review"]', '{"step":42,"surface":"validation"}', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_external_pilot_intake_submissions (id, submission_code, stakeholder_type, status, organization_name, contact_name, email, country, city, capabilities_json, repair_categories_json, motivation, pilot_fit_score, risk_level, triage_notes, source, created_at, updated_at, reviewed_by, reviewed_at)
VALUES
('pilot-intake-provider-bologna', 'PILOT-INTAKE-BASELINE-PROVIDER', 'provider', 'triaged', 'Bologna Distributed Repair Lab', 'Prototype Provider Lead', 'provider@example.test', 'Italy', 'Bologna', '["fdm_printing","petg","tpu","local_pickup"]', '["small_appliances","consumer_parts","plastic_components"]', 'Interested in joining a controlled Bologna repair pilot with local fulfilment governance.', 82, 'medium', 'Strong local fit. Requires provider agreement, quality checklist and payout review before activation.', 'seed', datetime('now'), datetime('now'), null, datetime('now')),
('pilot-intake-maker-baseline', 'PILOT-INTAKE-BASELINE-MAKER', 'maker', 'new', 'Independent Maker Studio', 'Prototype Maker Lead', 'maker@example.test', 'Italy', 'Remote', '["cad_modeling","reverse_engineering","model_iteration"]', '["replacement_parts","clips","brackets"]', 'Interested in contributing repair models and validating royalty/credit economics.', 74, 'medium', 'Good maker-economy test lead. IP/license review required.', 'seed', datetime('now'), datetime('now'), null, null);

INSERT OR IGNORE INTO platform_real_world_validation_cases (id, case_code, submission_id, status, repair_category, object_name, problem_statement, success_criteria_json, evidence_json, pilot_fit_score, governance_risk, owner, created_at, updated_at, reviewed_at)
VALUES
('validation-case-coffee-knob', 'REAL-CASE-BASELINE-COFFEE-KNOB', 'pilot-intake-provider-bologna', 'candidate', 'small_appliances', 'Coffee machine knob', 'Broken plastic selector knob with simple geometry and moderate thermal/mechanical requirements.', '["Object can be restored to functional use","Part can be printed or sourced safely","Customer acceptance evidence can be captured"]', '["demo_photo_placeholder","geometry_review_required","provider_quote_required"]', 78, 'medium', 'operations', datetime('now'), datetime('now'), null);

INSERT OR IGNORE INTO platform_pilot_stakeholder_lead_scores (id, submission_id, score, score_band, readiness_signal, strategic_fit_signal, risk_signal, notes, created_at)
VALUES
('lead-score-provider-bologna', 'pilot-intake-provider-bologna', 82, 'high', 'ready_for_review', 'local_supply_density', 'medium', 'Baseline provider lead is suitable for controlled real-world validation after legal/provider terms review.', datetime('now')),
('lead-score-maker-baseline', 'pilot-intake-maker-baseline', 74, 'medium', 'needs_review', 'maker_economy_signal', 'medium', 'Maker lead is useful for licensing/credits validation, not automatic public model publication.', datetime('now'));
