CREATE TABLE IF NOT EXISTS platform_demo_data_room_assets (
    id TEXT PRIMARY KEY,
    asset_key TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    category TEXT NOT NULL,
    audience TEXT NOT NULL DEFAULT 'investor',
    status TEXT NOT NULL DEFAULT 'draft',
    sensitivity TEXT NOT NULL DEFAULT 'internal',
    route_hint TEXT NOT NULL DEFAULT '',
    source_endpoint TEXT NOT NULL DEFAULT '',
    summary TEXT NOT NULL DEFAULT '',
    caveat TEXT NOT NULL DEFAULT '',
    owner TEXT NOT NULL DEFAULT 'operator',
    last_verified_at TEXT,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_pilot_launch_checklist_items (
    id TEXT PRIMARY KEY,
    item_key TEXT NOT NULL UNIQUE,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'medium',
    evidence_endpoint TEXT NOT NULL DEFAULT '',
    owner TEXT NOT NULL DEFAULT 'operator',
    due_label TEXT NOT NULL DEFAULT 'before_pilot',
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_stakeholder_feedback_loops (
    id TEXT PRIMARY KEY,
    loop_code TEXT NOT NULL UNIQUE,
    audience_type TEXT NOT NULL DEFAULT 'investor',
    stakeholder_name TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'open',
    objective TEXT NOT NULL DEFAULT '',
    related_demo_session_id TEXT,
    scheduled_at TEXT,
    notes TEXT NOT NULL DEFAULT '',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    closed_at TEXT,
    FOREIGN KEY (related_demo_session_id) REFERENCES platform_demo_sessions(id)
);

CREATE TABLE IF NOT EXISTS platform_stakeholder_feedback_items (
    id TEXT PRIMARY KEY,
    loop_id TEXT NOT NULL,
    audience_type TEXT NOT NULL DEFAULT 'investor',
    signal TEXT NOT NULL DEFAULT 'neutral',
    rating INTEGER NOT NULL DEFAULT 0,
    topic TEXT NOT NULL DEFAULT 'general',
    notes TEXT NOT NULL DEFAULT '',
    requested_action TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'open',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (loop_id) REFERENCES platform_stakeholder_feedback_loops(id)
);

CREATE TABLE IF NOT EXISTS platform_post_demo_reports (
    id TEXT PRIMARY KEY,
    report_code TEXT NOT NULL UNIQUE,
    loop_id TEXT,
    demo_session_id TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    executive_summary TEXT NOT NULL DEFAULT '',
    positives_json TEXT NOT NULL DEFAULT '[]',
    concerns_json TEXT NOT NULL DEFAULT '[]',
    follow_up_actions_json TEXT NOT NULL DEFAULT '[]',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    published_at TEXT,
    FOREIGN KEY (loop_id) REFERENCES platform_stakeholder_feedback_loops(id),
    FOREIGN KEY (demo_session_id) REFERENCES platform_demo_sessions(id)
);

CREATE TABLE IF NOT EXISTS platform_pilot_go_no_go_decisions (
    id TEXT PRIMARY KEY,
    decision_code TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft',
    decision TEXT NOT NULL DEFAULT 'conditional_go',
    score INTEGER NOT NULL DEFAULT 0,
    rationale TEXT NOT NULL DEFAULT '',
    conditions_json TEXT NOT NULL DEFAULT '[]',
    blockers_json TEXT NOT NULL DEFAULT '[]',
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT
);

CREATE TABLE IF NOT EXISTS platform_pilot_launch_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_demo_data_room_assets (id, asset_key, title, category, audience, status, sensitivity, route_hint, source_endpoint, summary, caveat, owner, last_verified_at, metadata_json, created_at, updated_at)
VALUES
('data-room-asset-product-book', 'product_book', 'Product Book and Repair Intelligence Positioning', 'product', 'investor', 'ready', 'internal', 'docs/02-product-book', '/api/health', 'Frames Re-born as a repair intelligence platform, not an STL marketplace.', 'Narrative is strategic and must be backed by pilot data before external claims.', 'founder', datetime('now'), '{"format":"markdown","source":"repository"}', datetime('now'), datetime('now')),
('data-room-asset-guided-demo', 'guided_demo_walkthrough', 'Guided Repair Journey Walkthrough', 'demo', 'investor', 'ready', 'internal', '#/demo-walkthrough', '/api/v1/platform/demo-walkthrough', 'Step-by-step demo mode for intake, AI governance, path decision, geometry, routing, proof, customer care and impact.', 'Local/pilot demo only. AI, payments, logistics and sustainability flows remain caveated.', 'operator', datetime('now'), '{"step":40}', datetime('now'), datetime('now')),
('data-room-asset-ci-evidence', 'ci_release_evidence', 'CI Smoke Matrix and Quality Gate Evidence', 'quality', 'technical', 'ready', 'internal', 'storage/logs/ci-release-evidence.json', '/api/v1/platform/smoke-tests', 'Release evidence and smoke test matrix for the current vertical slice.', 'Evidence is generated per CI run and must match the latest commit SHA.', 'engineering', datetime('now'), '{"artifact":"reborn-ci-release-evidence"}', datetime('now'), datetime('now')),
('data-room-asset-investor-kpis', 'investor_kpi_board_pack', 'Investor KPI and Board Reporting Pack', 'investor', 'investor', 'ready', 'internal', '#/investor-reporting', '/api/v1/platform/investor-reporting', 'KPI snapshots, demo narrative, board reports and readiness review for investor conversations.', 'KPI values are local/pilot evidence, not audited financials or certified ESG claims.', 'founder', datetime('now'), '{"step":37}', datetime('now'), datetime('now')),
('data-room-asset-pilot-readiness', 'pilot_launch_checklist', 'Pilot Launch Checklist', 'pilot', 'operator', 'draft', 'internal', '#/pilot-launch', '/api/v1/platform/pilot-launch', 'Operational checklist for beta/pilot readiness, stakeholder feedback and go/no-go decision.', 'Pilot approval requires explicit legal, provider, payment, support and security caveats.', 'operator', datetime('now'), '{"step":41}', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_pilot_launch_checklist_items (id, item_key, category, title, status, priority, evidence_endpoint, owner, due_label, notes, created_at, updated_at)
VALUES
('pilot-check-ci-step40', 'step40_ci_validated', 'quality_gate', 'Step 40 GitHub Actions run passes with 31 smoke scripts', 'open', 'critical', 'storage/logs/ci-quality-gate.json', 'engineering', 'before_step41_acceptance', 'Do not treat Step 41 as validated until Step 40 baseline has passed in CI.', datetime('now'), datetime('now')),
('pilot-check-demo-script', 'demo_script_caveats_reviewed', 'demo', 'Demo script contains explicit AI, payment, logistics, warranty and sustainability caveats', 'ready', 'high', '/api/v1/platform/demo-walkthrough', 'operator', 'before_external_demo', 'Use guided repair journey, not feature hopping.', datetime('now'), datetime('now')),
('pilot-check-data-room', 'data_room_minimum_pack_ready', 'data_room', 'Minimum data room pack is ready for investor/partner review', 'ready', 'high', '/api/v1/platform/data-room-assets', 'founder', 'before_external_demo', 'Keep source links and caveats visible.', datetime('now'), datetime('now')),
('pilot-check-provider-list', 'pilot_provider_shortlist', 'provider', 'Pilot provider shortlist and routing assumptions are reviewed', 'open', 'critical', '/api/v1/platform/provider-routing', 'operations', 'before_private_beta', 'Real providers, KYB and service terms remain outside local demo.', datetime('now'), datetime('now')),
('pilot-check-legal', 'legal_privacy_terms_review', 'legal', 'Privacy, warranty, liability and refund language reviewed for beta', 'open', 'critical', '/api/v1/platform/privacy-governance', 'legal', 'before_private_beta', 'Do not launch with real customer commitments before legal review.', datetime('now'), datetime('now')),
('pilot-check-support', 'support_and_feedback_loop_ready', 'support', 'Stakeholder feedback loop and post-demo report workflow are ready', 'ready', 'medium', '/api/v1/platform/stakeholder-feedback-loops', 'operator', 'before_external_demo', 'Feedback must produce next actions and a go/no-go decision.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_stakeholder_feedback_loops (id, loop_code, audience_type, stakeholder_name, status, objective, related_demo_session_id, scheduled_at, notes, created_by, created_at, updated_at, closed_at)
VALUES
('feedback-loop-baseline-step41', 'FEEDBACK-BASELINE-STEP41', 'investor', 'Baseline stakeholder panel', 'open', 'Collect structured reactions to the guided repair journey and pilot launch proposal.', null, null, 'Seed loop for Step 41 smoke and prototype demo.', null, datetime('now'), datetime('now'), null);

INSERT OR IGNORE INTO platform_stakeholder_feedback_items (id, loop_id, audience_type, signal, rating, topic, notes, requested_action, status, created_by, created_at, updated_at)
VALUES
('feedback-item-baseline-step41', 'feedback-loop-baseline-step41', 'investor', 'neutral', 7, 'pilot_scope', 'Baseline feedback item: the demo is clear but needs provider and legal validation before beta.', 'Prepare pilot shortlist and legal/privacy checklist.', 'open', null, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_post_demo_reports (id, report_code, loop_id, demo_session_id, status, executive_summary, positives_json, concerns_json, follow_up_actions_json, created_by, created_at, updated_at, published_at)
VALUES
('post-demo-report-baseline-step41', 'POST-DEMO-BASELINE-STEP41', 'feedback-loop-baseline-step41', null, 'draft', 'Baseline Step 41 report: guided demo is suitable for controlled stakeholder conversations with explicit caveats.', '["Clear repair journey narrative","Strong governance and CI evidence"]', '["Real AI and fulfilment integrations remain mock","Provider/legal validation required before beta"]', '["Validate Step 40 CI","Prepare pilot provider shortlist","Collect stakeholder feedback after each demo"]', null, datetime('now'), datetime('now'), null);

INSERT OR IGNORE INTO platform_pilot_go_no_go_decisions (id, decision_code, status, decision, score, rationale, conditions_json, blockers_json, created_by, reviewed_by, created_at, updated_at, reviewed_at)
VALUES
('pilot-decision-baseline-step41', 'PILOT-GATE-BASELINE-STEP41', 'draft', 'conditional_go', 68, 'Baseline decision: continue toward stakeholder demos and private pilot preparation, but do not launch real beta commitments yet.', '["Step 40 CI must pass","Provider shortlist must be reviewed","Legal/privacy/warranty language must be approved"]', '["Real payments and fulfilment remain mock","Production deployment not yet complete"]', null, null, datetime('now'), datetime('now'), null);
