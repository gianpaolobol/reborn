CREATE TABLE IF NOT EXISTS platform_demo_modes (
    id TEXT PRIMARY KEY,
    mode_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    audience TEXT NOT NULL DEFAULT 'investor',
    status TEXT NOT NULL DEFAULT 'active',
    objective TEXT NOT NULL,
    caveat TEXT NOT NULL,
    default_language TEXT NOT NULL DEFAULT 'en',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_guided_walkthrough_steps (
    id TEXT PRIMARY KEY,
    mode_id TEXT NOT NULL,
    step_key TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    title TEXT NOT NULL,
    narrative TEXT NOT NULL,
    route_hint TEXT NOT NULL,
    api_endpoint TEXT NOT NULL,
    primary_asset TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    expected_outcome TEXT NOT NULL,
    evidence_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(mode_id, step_key),
    FOREIGN KEY (mode_id) REFERENCES platform_demo_modes(id)
);

CREATE TABLE IF NOT EXISTS platform_demo_sessions (
    id TEXT PRIMARY KEY,
    session_code TEXT NOT NULL UNIQUE,
    mode_id TEXT NOT NULL,
    audience TEXT NOT NULL DEFAULT 'investor',
    status TEXT NOT NULL DEFAULT 'draft',
    presenter_name TEXT,
    current_step_key TEXT,
    notes TEXT NOT NULL DEFAULT '',
    created_by TEXT,
    started_at TEXT,
    completed_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (mode_id) REFERENCES platform_demo_modes(id)
);

CREATE TABLE IF NOT EXISTS platform_demo_session_events (
    id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    step_id TEXT,
    event_type TEXT NOT NULL,
    outcome TEXT NOT NULL DEFAULT 'recorded',
    notes TEXT NOT NULL DEFAULT '',
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (session_id) REFERENCES platform_demo_sessions(id),
    FOREIGN KEY (step_id) REFERENCES platform_guided_walkthrough_steps(id)
);

CREATE TABLE IF NOT EXISTS platform_demo_feedback (
    id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    audience_type TEXT NOT NULL DEFAULT 'investor',
    rating INTEGER NOT NULL DEFAULT 0,
    signal TEXT NOT NULL DEFAULT 'neutral',
    notes TEXT NOT NULL DEFAULT '',
    next_action TEXT NOT NULL DEFAULT '',
    created_by TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (session_id) REFERENCES platform_demo_sessions(id)
);

CREATE TABLE IF NOT EXISTS platform_demo_readiness_reviews (
    id TEXT PRIMARY KEY,
    review_code TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'open',
    readiness_level TEXT NOT NULL DEFAULT 'demo_ready_with_caveats',
    score INTEGER NOT NULL DEFAULT 0,
    checklist_json TEXT NOT NULL DEFAULT '[]',
    blockers_json TEXT NOT NULL DEFAULT '[]',
    recommended_script_json TEXT NOT NULL DEFAULT '[]',
    notes TEXT NOT NULL DEFAULT '',
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT
);

CREATE TABLE IF NOT EXISTS platform_demo_walkthrough_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_demo_modes (id, mode_key, name, audience, status, objective, caveat, default_language, created_at, updated_at)
VALUES
('demo-mode-investor-walkthrough', 'investor_walkthrough', 'Investor Guided Repair Journey', 'investor', 'active', 'Present the complete Re-born repair journey from broken object intake to impact and investor KPIs.', 'Local/pilot demo only: AI, payments, logistics, sustainability and legal claims remain mock or governance-only until explicitly validated.', 'en', datetime('now'), datetime('now')),
('demo-mode-beta-pilot', 'beta_pilot_walkthrough', 'Beta Pilot Operator Walkthrough', 'operator', 'active', 'Show how an operator can govern repair intake, routing, dispatch, customer care and release evidence.', 'Pilot governance flow only; real fulfilment, customer commitments and legal warranty terms remain out of scope.', 'en', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_guided_walkthrough_steps (id, mode_id, step_key, sort_order, title, narrative, route_hint, api_endpoint, primary_asset, status, expected_outcome, evidence_json, created_at, updated_at)
VALUES
('demo-step-01-intake', 'demo-mode-investor-walkthrough', 'broken_object_intake', 10, 'Broken object intake', 'Start with the user problem: an object must work again. The demo anchors on repair case intake rather than file browsing.', '#/repair-intake', '/api/v1/repair-cases', 'Objects Saved', 'active', 'Audience understands the user outcome and why Re-born is not a generic STL marketplace.', '["repair_case_intake","ownership"]', datetime('now'), datetime('now')),
('demo-step-02-ai', 'demo-mode-investor-walkthrough', 'ai_recognition', 20, 'AI diagnosis and provider-safe governance', 'Show mock AI recognition, then explain AI governance and provider sandbox controls before real Meshy/Trellis/Rodin integrations.', '#/ai-governance', '/api/v1/platform/ai-governance', 'AI Learning', 'active', 'Audience sees AI as governed assistance, not uncontrolled generation.', '["ai_pipeline_governance","ai_provider_sandbox"]', datetime('now'), datetime('now')),
('demo-step-03-path', 'demo-mode-investor-walkthrough', 'repair_path_decision', 30, 'Repair path decision', 'Demonstrate how Re-born chooses repair paths and keeps the user focused on restoring function.', '#/repair-paths', '/api/v1/repair-paths', 'Knowledge Graph', 'active', 'Audience sees the repair intelligence layer behind the journey.', '["repair_path_decision","knowledge_graph_feedback"]', datetime('now'), datetime('now')),
('demo-step-04-geometry', 'demo-mode-investor-walkthrough', 'geometry_printability', 40, 'CAD geometry and printability governance', 'Show that generated or maker-supplied geometry must pass format, printability and human review gates before routing.', '#/geometry-printability', '/api/v1/platform/geometry-printability', 'Provider Quality', 'active', 'Audience understands quality gates before provider fulfilment.', '["cad_geometry_validation","printability_governance"]', datetime('now'), datetime('now')),
('demo-step-05-routing', 'demo-mode-investor-walkthrough', 'provider_routing', 50, 'Provider capability and routing', 'Show how provider capability and machine profiles turn a repair need into a compatible fulfilment route.', '#/provider-routing', '/api/v1/platform/provider-routing', 'Marketplace Liquidity', 'active', 'Audience sees liquidity and provider quality management.', '["provider_routing","machine_profile_governance"]', datetime('now'), datetime('now')),
('demo-step-06-fulfilment', 'demo-mode-investor-walkthrough', 'dispatch_proof', 60, 'Dispatch and proof of repair', 'Show dispatch, shipment/pickup tracking and proof-of-repair records as the bridge between digital flow and real object recovery.', '#/dispatch-governance', '/api/v1/platform/dispatch-governance', 'Objects Saved', 'active', 'Audience sees operational proof, not just simulated orders.', '["dispatch_governance","proof_of_repair_governance"]', datetime('now'), datetime('now')),
('demo-step-07-care', 'demo-mode-investor-walkthrough', 'customer_acceptance', 70, 'Customer acceptance and post-repair care', 'Close the customer loop with acceptance, warranty placeholders, support tickets and feedback.', '#/customer-care', '/api/v1/platform/customer-care-governance', 'Operational Trust', 'active', 'Audience sees how Re-born handles trust after repair delivery.', '["customer_acceptance","post_repair_support"]', datetime('now'), datetime('now')),
('demo-step-08-impact', 'demo-mode-investor-walkthrough', 'impact_kpis', 80, 'Impact and investor KPIs', 'Translate completed repairs into sustainability impact, circularity metrics, board evidence and caveated investor narrative.', '#/investor-reporting', '/api/v1/platform/investor-reporting', 'Enterprise Value', 'active', 'Audience sees a credible, caveated investor story based on operational evidence.', '["sustainability_impact","investor_reporting","quality_gate"]', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_demo_readiness_reviews (id, review_code, status, readiness_level, score, checklist_json, blockers_json, recommended_script_json, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at)
VALUES
('demo-readiness-baseline-step40', 'DEMO-READY-BASELINE', 'open', 'demo_ready_with_caveats', 78, '["CI smoke suite must pass","Presenter must disclose AI/payment/logistics/sustainability caveats","Demo should follow the guided repair journey rather than feature-hopping","Step 39 quality gate evidence should be available"]', '["Real AI integrations remain sandboxed","Payments and payout flows remain mock","Sustainability impact remains pilot estimate"]', '["Open with broken object outcome","Show governed AI and geometry gates","Route to compatible provider","Close with proof, customer acceptance, impact and KPI caveats"]', 'Baseline Step 40 demo readiness review. The platform is ready for guided local/investor demo with explicit caveats, not for production claims.', null, null, datetime('now'), datetime('now'), null);
