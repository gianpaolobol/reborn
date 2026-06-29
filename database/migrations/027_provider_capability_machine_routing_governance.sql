CREATE TABLE IF NOT EXISTS platform_provider_capability_profiles (
    id TEXT PRIMARY KEY,
    provider_id TEXT NOT NULL,
    provider_name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    process TEXT NOT NULL DEFAULT 'fdm_3d_printing',
    materials_json TEXT NOT NULL DEFAULT '[]',
    capabilities_json TEXT NOT NULL DEFAULT '{}',
    max_build_volume_mm TEXT NOT NULL DEFAULT '{"x":270,"y":270,"z":270}',
    tolerance_class TEXT NOT NULL DEFAULT 'standard_repair',
    average_lead_time_days INTEGER NOT NULL DEFAULT 5,
    base_setup_fee_cents INTEGER NOT NULL DEFAULT 500,
    price_per_cm3_cents INTEGER NOT NULL DEFAULT 35,
    quality_score REAL NOT NULL DEFAULT 70,
    locality_score REAL NOT NULL DEFAULT 60,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_machine_profiles (
    id TEXT PRIMARY KEY,
    provider_capability_id TEXT NOT NULL,
    machine_code TEXT NOT NULL UNIQUE,
    machine_name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    process TEXT NOT NULL DEFAULT 'fdm_3d_printing',
    materials_json TEXT NOT NULL DEFAULT '[]',
    build_volume_mm TEXT NOT NULL DEFAULT '{"x":270,"y":270,"z":270}',
    nozzle_diameter_mm REAL NOT NULL DEFAULT 0.4,
    min_layer_height_mm REAL NOT NULL DEFAULT 0.12,
    max_layer_height_mm REAL NOT NULL DEFAULT 0.28,
    reliability_score REAL NOT NULL DEFAULT 75,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (provider_capability_id) REFERENCES platform_provider_capability_profiles(id)
);

CREATE TABLE IF NOT EXISTS platform_routing_policies (
    id TEXT PRIMARY KEY,
    policy_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    priority INTEGER NOT NULL DEFAULT 100,
    description TEXT NOT NULL,
    rules_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_fulfilment_routing_requests (
    id TEXT PRIMARY KEY,
    request_code TEXT NOT NULL UNIQUE,
    geometry_asset_id TEXT,
    repair_case_id TEXT,
    requested_process TEXT NOT NULL DEFAULT 'fdm_3d_printing',
    material_family TEXT NOT NULL DEFAULT 'pla_petg',
    quantity INTEGER NOT NULL DEFAULT 1,
    priority TEXT NOT NULL DEFAULT 'normal',
    destination_country TEXT NOT NULL DEFAULT 'IT',
    max_lead_time_days INTEGER NOT NULL DEFAULT 7,
    max_budget_cents INTEGER NOT NULL DEFAULT 4000,
    status TEXT NOT NULL DEFAULT 'draft',
    decision TEXT,
    routing_context_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_provider_routing_matches (
    id TEXT PRIMARY KEY,
    routing_request_id TEXT NOT NULL,
    provider_capability_id TEXT NOT NULL,
    machine_profile_id TEXT,
    status TEXT NOT NULL DEFAULT 'candidate',
    rank INTEGER NOT NULL DEFAULT 99,
    match_score INTEGER NOT NULL DEFAULT 0,
    estimated_lead_time_days INTEGER NOT NULL DEFAULT 0,
    estimated_cost_cents INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'EUR',
    fit_checks_json TEXT NOT NULL DEFAULT '{}',
    match_reasons_json TEXT NOT NULL DEFAULT '[]',
    risks_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (routing_request_id) REFERENCES platform_fulfilment_routing_requests(id),
    FOREIGN KEY (provider_capability_id) REFERENCES platform_provider_capability_profiles(id),
    FOREIGN KEY (machine_profile_id) REFERENCES platform_machine_profiles(id)
);

CREATE TABLE IF NOT EXISTS platform_routing_review_items (
    id TEXT PRIMARY KEY,
    routing_request_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'medium',
    review_reason TEXT NOT NULL,
    assigned_to TEXT,
    decision TEXT,
    notes TEXT,
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT,
    FOREIGN KEY (routing_request_id) REFERENCES platform_fulfilment_routing_requests(id)
);

CREATE TABLE IF NOT EXISTS platform_provider_routing_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_provider_capability_profiles (id, provider_id, provider_name, status, process, materials_json, capabilities_json, max_build_volume_mm, tolerance_class, average_lead_time_days, base_setup_fee_cents, price_per_cm3_cents, quality_score, locality_score, created_at, updated_at)
VALUES
('provider-cap-bologna-fdm', 'provider-bologna-lab', 'Bologna Repair Lab', 'active', 'fdm_3d_printing', '["pla_petg","tpu","asa"]', '{"cad_validation":true,"human_review":true,"local_pickup":true}', '{"x":270,"y":270,"z":270}', 'functional_repair', 3, 450, 38, 88.0, 92.0, datetime('now'), datetime('now')),
('provider-cap-milan-fdm-sla', 'provider-milan-maker', 'Milano Distributed Manufacturing', 'active', 'fdm_3d_printing', '["pla_petg","asa","abs"]', '{"small_batch":true,"post_processing":true,"rush_jobs":true}', '{"x":250,"y":250,"z":250}', 'small_batch_repair', 4, 650, 42, 84.0, 75.0, datetime('now'), datetime('now')),
('provider-cap-barcelona-circular', 'provider-barcelona-circular', 'Barcelona Circular Fab', 'active', 'fdm_3d_printing', '["pla_petg","recycled_petg"]', '{"repair_validation":true,"sustainability_evidence":true}', '{"x":220,"y":220,"z":220}', 'circular_repair', 5, 500, 34, 81.0, 68.0, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_machine_profiles (id, provider_capability_id, machine_code, machine_name, status, process, materials_json, build_volume_mm, nozzle_diameter_mm, min_layer_height_mm, max_layer_height_mm, reliability_score, notes, created_at, updated_at)
VALUES
('machine-bologna-x1c', 'provider-cap-bologna-fdm', 'BO-FDM-X1C-001', 'Bambu Lab X1 Carbon pilot profile', 'active', 'fdm_3d_printing', '["pla_petg","asa"]', '{"x":256,"y":256,"z":256}', 0.4, 0.08, 0.28, 91.0, 'Pilot machine profile for functional repair prototypes.', datetime('now'), datetime('now')),
('machine-bologna-u1-flex', 'provider-cap-bologna-fdm', 'BO-FDM-U1-TPU-001', 'Snapmaker U1 flexible materials pilot profile', 'active', 'fdm_3d_printing', '["pla_petg","tpu"]', '{"x":270,"y":270,"z":270}', 0.4, 0.12, 0.28, 84.0, 'Flexible material and repair service profile.', datetime('now'), datetime('now')),
('machine-milan-fdm-batch', 'provider-cap-milan-fdm-sla', 'MI-FDM-BATCH-001', 'Milan small batch FDM profile', 'active', 'fdm_3d_printing', '["pla_petg","asa","abs"]', '{"x":250,"y":250,"z":250}', 0.4, 0.10, 0.30, 86.0, 'Small batch provider routing profile.', datetime('now'), datetime('now')),
('machine-barcelona-circular', 'provider-cap-barcelona-circular', 'BCN-FDM-CIRC-001', 'Barcelona circular PETG profile', 'active', 'fdm_3d_printing', '["pla_petg","recycled_petg"]', '{"x":220,"y":220,"z":220}', 0.4, 0.12, 0.28, 82.0, 'Circular repair network profile.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_routing_policies (id, policy_key, name, status, priority, description, rules_json, created_at, updated_at)
VALUES
('routing-policy-geometry-approved', 'geometry_must_be_validated', 'Geometry must be validated before routing', 'active', 10, 'Provider routing should prefer assets already approved or reviewed by the geometry governance layer.', '{"allowed_geometry_statuses":["validated","reviewed_approved","reviewed_with_notes"],"review_required_if_unvalidated":true}', datetime('now'), datetime('now')),
('routing-policy-capability-match', 'process_material_machine_fit', 'Process/material/machine fit required', 'active', 20, 'A routing match must fit requested process, material family and machine build volume.', '{"require_process_match":true,"require_material_match":true,"require_build_volume_fit":true}', datetime('now'), datetime('now')),
('routing-policy-human-route', 'human_review_for_low_confidence_route', 'Human review for low confidence routes', 'active', 30, 'Low score or no-match routing decisions must generate an operator review item.', '{"minimum_auto_route_score":70,"review_below_score":70}', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_fulfilment_routing_requests (id, request_code, geometry_asset_id, repair_case_id, requested_process, material_family, quantity, priority, destination_country, max_lead_time_days, max_budget_cents, status, decision, routing_context_json, created_by, created_at, updated_at)
VALUES
('route-req-demo-hinge', 'ROUTE-DEMO-HINGE-001', 'geom-asset-demo-hinge', NULL, 'fdm_3d_printing', 'pla_petg', 1, 'normal', 'IT', 7, 3500, 'draft', NULL, '{"origin":"step33_seed","repair_context":"washing machine hinge replacement"}', NULL, datetime('now'), datetime('now'));
