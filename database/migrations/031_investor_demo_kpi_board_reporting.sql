CREATE TABLE IF NOT EXISTS platform_investor_kpi_definitions (
    id TEXT PRIMARY KEY,
    kpi_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    unit TEXT NOT NULL DEFAULT 'count',
    source_query TEXT NOT NULL DEFAULT 'manual_or_local_aggregate',
    narrative_role TEXT NOT NULL,
    calculation_notes TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_investor_kpi_snapshots (
    id TEXT PRIMARY KEY,
    snapshot_code TEXT NOT NULL UNIQUE,
    scope TEXT NOT NULL DEFAULT 'investor_demo',
    status TEXT NOT NULL DEFAULT 'draft',
    objects_saved INTEGER NOT NULL DEFAULT 0,
    repair_cases INTEGER NOT NULL DEFAULT 0,
    providers INTEGER NOT NULL DEFAULT 0,
    maker_profiles INTEGER NOT NULL DEFAULT 0,
    model_assets INTEGER NOT NULL DEFAULT 0,
    repair_bounties INTEGER NOT NULL DEFAULT 0,
    revenue_credit_balance INTEGER NOT NULL DEFAULT 0,
    payout_runs INTEGER NOT NULL DEFAULT 0,
    ai_jobs INTEGER NOT NULL DEFAULT 0,
    geometry_validations INTEGER NOT NULL DEFAULT 0,
    routing_matches INTEGER NOT NULL DEFAULT 0,
    dispatches INTEGER NOT NULL DEFAULT 0,
    accepted_repairs INTEGER NOT NULL DEFAULT 0,
    co2e_avoided_kg REAL NOT NULL DEFAULT 0,
    waste_diverted_kg REAL NOT NULL DEFAULT 0,
    readiness_status TEXT NOT NULL DEFAULT 'unknown',
    demo_score INTEGER NOT NULL DEFAULT 0,
    metrics_json TEXT NOT NULL DEFAULT '{}',
    generated_by TEXT,
    generated_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_demo_narrative_sections (
    id TEXT PRIMARY KEY,
    section_key TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    audience TEXT NOT NULL DEFAULT 'investor',
    status TEXT NOT NULL DEFAULT 'active',
    sort_order INTEGER NOT NULL DEFAULT 0,
    headline TEXT NOT NULL,
    narrative TEXT NOT NULL,
    proof_points_json TEXT NOT NULL DEFAULT '[]',
    risk_note TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_board_reports (
    id TEXT PRIMARY KEY,
    report_code TEXT NOT NULL UNIQUE,
    report_type TEXT NOT NULL DEFAULT 'investor_demo',
    status TEXT NOT NULL DEFAULT 'draft',
    title TEXT NOT NULL,
    period_label TEXT NOT NULL,
    kpi_snapshot_id TEXT,
    executive_summary TEXT NOT NULL,
    risks_json TEXT NOT NULL DEFAULT '[]',
    asks_json TEXT NOT NULL DEFAULT '[]',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    published_at TEXT,
    FOREIGN KEY (kpi_snapshot_id) REFERENCES platform_investor_kpi_snapshots(id)
);

CREATE TABLE IF NOT EXISTS platform_board_report_sections (
    id TEXT PRIMARY KEY,
    board_report_id TEXT NOT NULL,
    section_key TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    evidence_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (board_report_id) REFERENCES platform_board_reports(id)
);

CREATE TABLE IF NOT EXISTS platform_board_report_evidence (
    id TEXT PRIMARY KEY,
    board_report_id TEXT,
    evidence_type TEXT NOT NULL,
    source_entity_type TEXT NOT NULL,
    source_entity_id TEXT,
    title TEXT NOT NULL,
    summary TEXT NOT NULL,
    confidence_level TEXT NOT NULL DEFAULT 'pilot_evidence',
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (board_report_id) REFERENCES platform_board_reports(id)
);

CREATE TABLE IF NOT EXISTS platform_investor_demo_readiness_reviews (
    id TEXT PRIMARY KEY,
    review_code TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'open',
    readiness_level TEXT NOT NULL DEFAULT 'demo_ready_with_caveats',
    score INTEGER NOT NULL DEFAULT 0,
    blocking_issues_json TEXT NOT NULL DEFAULT '[]',
    recommended_next_steps_json TEXT NOT NULL DEFAULT '[]',
    notes TEXT NOT NULL,
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT
);

CREATE TABLE IF NOT EXISTS platform_investor_reporting_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_investor_kpi_definitions (id, kpi_key, name, category, status, unit, source_query, narrative_role, calculation_notes, metadata_json, created_at, updated_at)
VALUES
('investor-kpi-objects-saved', 'objects_saved', 'Objects saved', 'impact', 'active', 'count', 'platform_repair_impact_records', 'Proves the mission: allow anyone to repair anything.', 'Counts calculated/accepted impact records with repair_score >= 50.', '{"step":37,"public_claim":false}', datetime('now'), datetime('now')),
('investor-kpi-repair-cases', 'repair_cases', 'Repair cases created', 'traction', 'active', 'count', 'repair_cases', 'Shows user problem volume in the repair journey.', 'Counts local repair cases in the pilot database.', '{"step":37,"public_claim":false}', datetime('now'), datetime('now')),
('investor-kpi-provider-network', 'providers', 'Provider network size', 'marketplace_liquidity', 'active', 'count', 'providers/platform_partners', 'Shows supply-side capacity.', 'Combines seed providers and partner/provider onboarding governance.', '{"step":37,"public_claim":false}', datetime('now'), datetime('now')),
('investor-kpi-maker-assets', 'maker_assets', 'Maker model assets', 'maker_economy', 'active', 'count', 'platform_repair_model_assets', 'Shows maker economy potential without reducing Re-born to STL browsing.', 'Counts governed model assets in the maker economy layer.', '{"step":37,"public_claim":false}', datetime('now'), datetime('now')),
('investor-kpi-ai-jobs', 'ai_jobs', 'AI orchestration jobs', 'ai', 'active', 'count', 'platform_ai_orchestration_jobs', 'Shows readiness to connect Meshy/Trellis/Rodin safely.', 'Counts sandboxed AI jobs, not real provider calls.', '{"step":37,"public_claim":false}', datetime('now'), datetime('now')),
('investor-kpi-co2e', 'co2e_avoided_kg', 'CO2e avoided pilot estimate', 'impact', 'active', 'kg_co2e', 'platform_repair_impact_records', 'Shows sustainability upside with caveats.', 'Pilot estimate only; not certified LCA or public ESG claim.', '{"step":37,"public_claim":false}', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_demo_narrative_sections (id, section_key, title, audience, status, sort_order, headline, narrative, proof_points_json, risk_note, created_at, updated_at)
VALUES
('demo-section-problem', 'problem', 'Problem', 'investor', 'active', 10, 'Repair is fragmented, opaque and often abandoned.', 'Users do not want files: they want broken objects to work again. Re-born turns diagnosis, maker knowledge, provider routing and fulfilment governance into one repair journey.', '["Repair case intake","AI diagnosis mock","Provider routing governance"]', 'Market validation still requires real pilot cohorts and provider feedback.', datetime('now'), datetime('now')),
('demo-section-solution', 'solution', 'Solution', 'investor', 'active', 20, 'A repair operating system, not a file marketplace.', 'The platform combines AI, knowledge graph, maker economy, provider capacity, payment/order mock workflows, trust, governance and operational readiness.', '["End-to-end repair journey","Human review gates","Operational governance layers"]', 'Several components remain local/mock and require production integrations.', datetime('now'), datetime('now')),
('demo-section-moat', 'moat', 'Moat', 'investor', 'active', 30, 'Every repair can improve the system.', 'Completed repairs feed learning events, trust scoring, provider quality, maker rewards, geometry governance and sustainability impact intelligence.', '["Knowledge feedback","Provider quality score","Impact metrics"]', 'Real data volume is still required to validate learning effects.', datetime('now'), datetime('now')),
('demo-section-business-model', 'business_model', 'Business model', 'investor', 'active', 40, 'Multiple repair-native revenue streams.', 'Re-born can earn from platform fees, provider services, maker credits/royalties, enterprise workflows and future AI/CAD tooling while keeping the user outcome as the anchor.', '["Fee policies","Credit ledger","Payout governance","Partner onboarding"]', 'Real payments, taxation, refunds and KYC/KYB are not enabled yet.', datetime('now'), datetime('now')),
('demo-section-readiness', 'readiness', 'Readiness', 'board', 'active', 50, 'Demo-ready with explicit caveats.', 'The platform can present a broad local MVP while clearly distinguishing ready, degraded, mock and out-of-scope production elements.', '["Readiness endpoint","Smoke tests","Incident/status workflows","Privacy governance"]', 'Beta entry requires passing smoke tests in the target environment and legal/privacy review.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_investor_demo_readiness_reviews (id, review_code, status, readiness_level, score, blocking_issues_json, recommended_next_steps_json, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at)
VALUES
('investor-readiness-baseline-step37', 'INV-READY-BASELINE', 'open', 'demo_ready_with_caveats', 72, '["Real AI integrations are still sandboxed","Payments/payouts are mock","Environmental claims are pilot estimates"]', '["Run full smoke suite","Prepare 5-minute investor demo path","Collect 3-5 pilot partner conversations","Validate legal/privacy notices"]', 'Baseline Step 37 investor demo readiness review. Local demo is broad; commercial beta still needs real integrations and legal/operational validation.', null, null, datetime('now'), datetime('now'), null);
