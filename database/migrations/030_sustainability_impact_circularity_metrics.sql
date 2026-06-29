CREATE TABLE IF NOT EXISTS platform_sustainability_factors (
    id TEXT PRIMARY KEY,
    factor_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    unit TEXT NOT NULL,
    default_value REAL NOT NULL DEFAULT 0,
    calculation_notes TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_repair_impact_records (
    id TEXT PRIMARY KEY,
    impact_code TEXT NOT NULL UNIQUE,
    acceptance_record_id TEXT,
    dispatch_id TEXT,
    proof_of_repair_id TEXT,
    repair_case_id TEXT,
    repair_order_id TEXT,
    category TEXT NOT NULL DEFAULT 'general_repair',
    status TEXT NOT NULL DEFAULT 'draft',
    object_weight_kg REAL NOT NULL DEFAULT 0,
    estimated_lifespan_months INTEGER NOT NULL DEFAULT 0,
    co2e_avoided_kg REAL NOT NULL DEFAULT 0,
    waste_diverted_kg REAL NOT NULL DEFAULT 0,
    material_saved_kg REAL NOT NULL DEFAULT 0,
    repair_score INTEGER NOT NULL DEFAULT 0,
    confidence_level TEXT NOT NULL DEFAULT 'pilot_estimate',
    evidence_json TEXT NOT NULL DEFAULT '{}',
    calculated_at TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (acceptance_record_id) REFERENCES platform_customer_acceptance_records(id),
    FOREIGN KEY (dispatch_id) REFERENCES platform_fulfilment_dispatches(id),
    FOREIGN KEY (proof_of_repair_id) REFERENCES platform_proof_of_repair_records(id)
);

CREATE TABLE IF NOT EXISTS platform_circularity_metric_snapshots (
    id TEXT PRIMARY KEY,
    snapshot_code TEXT NOT NULL UNIQUE,
    scope TEXT NOT NULL DEFAULT 'pilot',
    status TEXT NOT NULL DEFAULT 'draft',
    period_start TEXT,
    period_end TEXT,
    objects_saved INTEGER NOT NULL DEFAULT 0,
    accepted_repairs INTEGER NOT NULL DEFAULT 0,
    co2e_avoided_kg REAL NOT NULL DEFAULT 0,
    waste_diverted_kg REAL NOT NULL DEFAULT 0,
    material_saved_kg REAL NOT NULL DEFAULT 0,
    repair_credits_issued INTEGER NOT NULL DEFAULT 0,
    impact_score INTEGER NOT NULL DEFAULT 0,
    calculation_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_repair_outcome_insights (
    id TEXT PRIMARY KEY,
    insight_type TEXT NOT NULL,
    related_entity_type TEXT,
    related_entity_id TEXT,
    severity TEXT NOT NULL DEFAULT 'info',
    status TEXT NOT NULL DEFAULT 'open',
    title TEXT NOT NULL,
    summary TEXT NOT NULL,
    recommended_action TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    resolved_at TEXT
);

CREATE TABLE IF NOT EXISTS platform_impact_review_items (
    id TEXT PRIMARY KEY,
    related_entity_type TEXT NOT NULL,
    related_entity_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'medium',
    review_reason TEXT NOT NULL,
    decision TEXT,
    notes TEXT,
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT
);

CREATE TABLE IF NOT EXISTS platform_sustainability_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_sustainability_factors (id, factor_key, name, category, status, unit, default_value, calculation_notes, metadata_json, created_at, updated_at)
VALUES
('sustainability-factor-generic-co2', 'generic_repair_co2e_per_kg', 'Generic repair CO2e avoided per kg', 'co2e', 'active', 'kg_co2e_per_kg_object', 6.5, 'Pilot estimate used when no product-specific lifecycle factor is available.', '{"source":"pilot_assumption","requires_validation_before_public_claims":true}', datetime('now'), datetime('now')),
('sustainability-factor-electronics-co2', 'electronics_repair_co2e_per_kg', 'Electronics repair CO2e avoided per kg', 'co2e', 'active', 'kg_co2e_per_kg_object', 18.0, 'Higher placeholder factor for electronics where embodied carbon can be material.', '{"source":"pilot_assumption","requires_validation_before_public_claims":true}', datetime('now'), datetime('now')),
('sustainability-factor-plastic-co2', 'plastic_part_repair_co2e_per_kg', 'Plastic part repair CO2e avoided per kg', 'co2e', 'active', 'kg_co2e_per_kg_object', 3.2, 'Placeholder for plastic component replacement and repair journeys.', '{"source":"pilot_assumption","requires_validation_before_public_claims":true}', datetime('now'), datetime('now')),
('sustainability-factor-waste-diversion', 'waste_diversion_ratio', 'Waste diversion ratio', 'waste', 'active', 'ratio', 0.92, 'Share of object weight treated as diverted from waste stream when repair is accepted.', '{"source":"pilot_assumption","requires_validation_before_public_claims":true}', datetime('now'), datetime('now')),
('sustainability-factor-material-saved', 'material_saved_ratio', 'Material saved ratio', 'material', 'active', 'ratio', 0.78, 'Share of object weight counted as avoided replacement material in pilot reporting.', '{"source":"pilot_assumption","requires_validation_before_public_claims":true}', datetime('now'), datetime('now')),
('sustainability-factor-lifespan-score', 'lifespan_score_months', 'Lifespan score months', 'score', 'active', 'months', 24, 'Default lifespan extension target used to normalize pilot repair score.', '{"source":"pilot_assumption","requires_validation_before_public_claims":true}', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_repair_outcome_insights (id, insight_type, related_entity_type, related_entity_id, severity, status, title, summary, recommended_action, metadata_json, created_by, created_at, updated_at)
VALUES
('impact-insight-baseline-public-claims', 'sustainability_claim_risk', 'system', 'sustainability-impact', 'warning', 'open', 'Impact claims require evidence before public use', 'Step 36 creates local/pilot impact estimates. Public sustainability claims require validated factors, methodology notes and legal review.', 'Keep external claims qualified as pilot estimates until validated by a sustainability/legal review.', '{"step":36,"public_claims_allowed":false}', null, datetime('now'), datetime('now'));
