CREATE TABLE IF NOT EXISTS platform_geometry_validation_profiles (
    id TEXT PRIMARY KEY,
    profile_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    target_process TEXT NOT NULL,
    material_family TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    min_wall_thickness_mm REAL NOT NULL DEFAULT 1.2,
    min_feature_size_mm REAL NOT NULL DEFAULT 0.8,
    max_bounding_box_mm TEXT NOT NULL DEFAULT '{"x":270,"y":270,"z":270}',
    requires_human_review INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_geometry_assets (
    id TEXT PRIMARY KEY,
    asset_code TEXT NOT NULL UNIQUE,
    source_type TEXT NOT NULL,
    source_ref TEXT,
    file_name TEXT NOT NULL,
    file_format TEXT NOT NULL,
    repair_case_id TEXT,
    model_asset_id TEXT,
    ai_job_id TEXT,
    status TEXT NOT NULL DEFAULT 'submitted',
    declared_units TEXT NOT NULL DEFAULT 'mm',
    bounding_box_mm TEXT NOT NULL DEFAULT '{"x":0,"y":0,"z":0}',
    estimated_volume_cm3 REAL NOT NULL DEFAULT 0,
    estimated_surface_cm2 REAL NOT NULL DEFAULT 0,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_geometry_validation_runs (
    id TEXT PRIMARY KEY,
    run_code TEXT NOT NULL UNIQUE,
    geometry_asset_id TEXT NOT NULL,
    profile_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'completed',
    decision TEXT NOT NULL DEFAULT 'review_required',
    score INTEGER NOT NULL DEFAULT 0,
    checks_json TEXT NOT NULL DEFAULT '{}',
    summary TEXT NOT NULL,
    evaluated_by TEXT,
    evaluated_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (geometry_asset_id) REFERENCES platform_geometry_assets(id),
    FOREIGN KEY (profile_id) REFERENCES platform_geometry_validation_profiles(id)
);

CREATE TABLE IF NOT EXISTS platform_printability_rules (
    id TEXT PRIMARY KEY,
    rule_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    severity TEXT NOT NULL DEFAULT 'warning',
    category TEXT NOT NULL,
    description TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_printability_findings (
    id TEXT PRIMARY KEY,
    validation_run_id TEXT NOT NULL,
    rule_id TEXT NOT NULL,
    severity TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    message TEXT NOT NULL,
    location_hint TEXT,
    recommendation TEXT NOT NULL,
    created_at TEXT NOT NULL,
    resolved_at TEXT,
    FOREIGN KEY (validation_run_id) REFERENCES platform_geometry_validation_runs(id),
    FOREIGN KEY (rule_id) REFERENCES platform_printability_rules(id)
);

CREATE TABLE IF NOT EXISTS platform_geometry_review_items (
    id TEXT PRIMARY KEY,
    geometry_asset_id TEXT NOT NULL,
    validation_run_id TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    review_type TEXT NOT NULL DEFAULT 'human_geometry_review',
    priority TEXT NOT NULL DEFAULT 'medium',
    assigned_to TEXT,
    decision TEXT,
    notes TEXT,
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT,
    FOREIGN KEY (geometry_asset_id) REFERENCES platform_geometry_assets(id),
    FOREIGN KEY (validation_run_id) REFERENCES platform_geometry_validation_runs(id)
);

CREATE TABLE IF NOT EXISTS platform_geometry_governance_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_geometry_validation_profiles (id, profile_key, name, target_process, material_family, status, min_wall_thickness_mm, min_feature_size_mm, max_bounding_box_mm, requires_human_review, created_at, updated_at)
VALUES
('geom-profile-fdm-pla-petg', 'fdm_pla_petg_standard', 'FDM PLA/PETG standard repair part', 'fdm_3d_printing', 'pla_petg', 'active', 1.2, 0.8, '{"x":270,"y":270,"z":270}', 1, datetime('now'), datetime('now')),
('geom-profile-tpu-flex', 'fdm_tpu_flexible', 'FDM TPU flexible repair part', 'fdm_3d_printing', 'tpu', 'active', 1.6, 1.0, '{"x":220,"y":220,"z":220}', 1, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_printability_rules (id, rule_key, name, severity, category, description, status, created_at, updated_at)
VALUES
('print-rule-format', 'supported_file_format', 'Supported CAD/mesh format', 'critical', 'format', 'Only STL, OBJ, STEP, STP, 3MF and AMF are accepted in this pilot validation layer.', 'active', datetime('now'), datetime('now')),
('print-rule-bbox', 'machine_bounding_box', 'Machine bounding box', 'critical', 'geometry', 'The part must fit inside the selected provider or pilot machine build volume.', 'active', datetime('now'), datetime('now')),
('print-rule-wall', 'minimum_wall_thickness', 'Minimum wall thickness', 'warning', 'printability', 'Thin walls may fail or be unsafe for functional repair components.', 'active', datetime('now'), datetime('now')),
('print-rule-review', 'human_review_required', 'Human review before release', 'warning', 'governance', 'AI-generated or user-submitted repair geometry requires a human review before provider routing.', 'active', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_geometry_assets (id, asset_code, source_type, source_ref, file_name, file_format, status, declared_units, bounding_box_mm, estimated_volume_cm3, estimated_surface_cm2, metadata_json, created_by, created_at, updated_at)
VALUES
('geom-asset-demo-hinge', 'GEO-DEMO-HINGE-001', 'ai_artifact_stub', 'AI-JOB-DEMO', 'washing-machine-door-hinge-v1.stl', 'stl', 'submitted', 'mm', '{"x":82,"y":34,"z":18}', 21.4, 148.6, '{"repair_context":"functional hinge replacement","origin":"step32_seed"}', NULL, datetime('now'), datetime('now'));
