CREATE TABLE IF NOT EXISTS platform_ai_model_providers (
    id TEXT PRIMARY KEY,
    provider_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'mock',
    capability TEXT NOT NULL,
    execution_mode TEXT NOT NULL DEFAULT 'local_mock',
    requires_human_review INTEGER NOT NULL DEFAULT 1,
    risk_notes TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_model_providers_status
ON platform_ai_model_providers(status, capability);

CREATE TABLE IF NOT EXISTS platform_ai_pipeline_runs (
    id TEXT PRIMARY KEY,
    run_code TEXT NOT NULL UNIQUE,
    pipeline_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued',
    provider_key TEXT NOT NULL,
    repair_case_id TEXT NULL,
    source_type TEXT NOT NULL DEFAULT 'repair_case',
    source_ref TEXT NULL,
    input_summary TEXT NOT NULL,
    output_summary TEXT NULL,
    confidence_score INTEGER NOT NULL DEFAULT 0,
    risk_level TEXT NOT NULL DEFAULT 'medium',
    human_review_required INTEGER NOT NULL DEFAULT 1,
    reviewed_by TEXT NULL,
    reviewed_at TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_pipeline_runs_status
ON platform_ai_pipeline_runs(status, pipeline_type, created_at);

CREATE TABLE IF NOT EXISTS platform_ai_human_reviews (
    id TEXT PRIMARY KEY,
    pipeline_run_id TEXT NOT NULL,
    review_type TEXT NOT NULL DEFAULT 'operator_review',
    decision TEXT NOT NULL,
    quality_score INTEGER NOT NULL DEFAULT 0,
    safety_score INTEGER NOT NULL DEFAULT 0,
    dimensional_score INTEGER NOT NULL DEFAULT 0,
    notes TEXT NOT NULL,
    reviewed_by TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (pipeline_run_id) REFERENCES platform_ai_pipeline_runs(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_human_reviews_run
ON platform_ai_human_reviews(pipeline_run_id, created_at);

CREATE TABLE IF NOT EXISTS platform_ai_dataset_items (
    id TEXT PRIMARY KEY,
    source_type TEXT NOT NULL,
    source_ref TEXT NOT NULL,
    object_category TEXT NOT NULL,
    label TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'candidate',
    consent_status TEXT NOT NULL DEFAULT 'needs_review',
    license_status TEXT NOT NULL DEFAULT 'needs_review',
    quality_score INTEGER NOT NULL DEFAULT 0,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_dataset_items_status
ON platform_ai_dataset_items(status, object_category, created_at);

CREATE TABLE IF NOT EXISTS platform_ai_quality_evaluations (
    id TEXT PRIMARY KEY,
    evaluation_code TEXT NOT NULL UNIQUE,
    pipeline_type TEXT NOT NULL,
    sample_size INTEGER NOT NULL DEFAULT 0,
    pass_rate INTEGER NOT NULL DEFAULT 0,
    average_confidence INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft',
    risk_findings_json TEXT NOT NULL DEFAULT '[]',
    created_by TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_quality_evaluations_status
ON platform_ai_quality_evaluations(status, pipeline_type, created_at);

CREATE TABLE IF NOT EXISTS platform_ai_safety_rules (
    id TEXT PRIMARY KEY,
    rule_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    severity TEXT NOT NULL DEFAULT 'medium',
    applies_to TEXT NOT NULL,
    description TEXT NOT NULL,
    required_action TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_safety_rules_status
ON platform_ai_safety_rules(status, severity);

CREATE TABLE IF NOT EXISTS platform_ai_governance_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT NULL,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_governance_audit_log_subject
ON platform_ai_governance_audit_log(subject_type, subject_id, created_at);

INSERT OR IGNORE INTO platform_ai_model_providers (id, provider_key, name, status, capability, execution_mode, requires_human_review, risk_notes, created_at, updated_at)
VALUES
('ai-provider-mock-recognition', 'mock_recognition_engine', 'Mock Recognition Engine', 'mock', 'image_recognition', 'local_mock', 1, 'Local deterministic placeholder; no external AI call is made.', datetime('now'), datetime('now')),
('ai-provider-mock-model-generation', 'mock_model_generation_engine', 'Mock Model Generation Engine', 'mock', 'model_generation', 'local_mock', 1, 'Local governance placeholder for future Meshy/Trellis/Rodin style integrations.', datetime('now'), datetime('now')),
('ai-provider-human-review', 'human_review_board', 'Human Review Board', 'active', 'human_review', 'internal_workflow', 0, 'Operator review gate for diagnosis, safety and model usability.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_ai_safety_rules (id, rule_key, name, status, severity, applies_to, description, required_action, created_at, updated_at)
VALUES
('ai-rule-safety-critical-parts', 'safety_critical_parts_review', 'Safety critical parts require manual approval', 'active', 'high', 'model_generation', 'Parts for brakes, load-bearing components, medical devices or electrical safety must not be auto-approved.', 'Block auto-approval and require expert review.', datetime('now'), datetime('now')),
('ai-rule-low-confidence-diagnosis', 'low_confidence_diagnosis_review', 'Low confidence diagnosis review', 'active', 'medium', 'diagnosis', 'Diagnosis outputs below the confidence threshold must be reviewed before repair path decisions are trusted.', 'Route to human-in-the-loop review.', datetime('now'), datetime('now')),
('ai-rule-dataset-consent', 'dataset_consent_required', 'Dataset consent and license required', 'active', 'high', 'dataset', 'Images, CAD files and repair outcomes must have consent and licensing metadata before training use.', 'Keep as candidate until consent and license are approved.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_ai_pipeline_runs (id, run_code, pipeline_type, status, provider_key, repair_case_id, source_type, source_ref, input_summary, output_summary, confidence_score, risk_level, human_review_required, reviewed_by, reviewed_at, created_by, created_at, updated_at)
VALUES
('ai-run-demo-diagnosis-001', 'AI-RUN-DEMO-DIAGNOSIS-001', 'diagnosis', 'in_review', 'mock_recognition_engine', NULL, 'repair_case', 'demo-repair-case', 'Seed image recognition request for a cracked plastic appliance handle.', 'Likely broken handle; suggested repair path: maker model or provider replacement.', 72, 'medium', 1, NULL, NULL, NULL, datetime('now'), datetime('now')),
('ai-run-demo-model-001', 'AI-RUN-DEMO-MODEL-001', 'model_generation', 'queued', 'mock_model_generation_engine', NULL, 'repair_bounty', 'repair-bounty-laundry-knob-001', 'Generate printable replacement concept for selector knob bounty.', NULL, 0, 'high', 1, NULL, NULL, NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_ai_dataset_items (id, source_type, source_ref, object_category, label, status, consent_status, license_status, quality_score, metadata_json, created_by, created_at, updated_at)
VALUES
('ai-dataset-item-demo-001', 'repair_outcome', 'learning-event-demo', 'appliance', 'broken_handle_repaired', 'candidate', 'approved', 'pilot_internal', 78, '{"source":"seed","training_use":"dry_run_only"}', NULL, datetime('now'), datetime('now')),
('ai-dataset-item-demo-002', 'model_asset', 'model-asset-garmin-strap-connector', 'wearable', 'strap_connector_insert', 'approved', 'approved', 'repair_credit_pilot', 86, '{"source":"seed","training_use":"metadata_only"}', NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_ai_quality_evaluations (id, evaluation_code, pipeline_type, sample_size, pass_rate, average_confidence, status, risk_findings_json, created_by, created_at)
VALUES
('ai-quality-eval-demo-001', 'AI-EVAL-DEMO-001', 'diagnosis', 12, 75, 71, 'review_required', '["low sample size", "mock engine only"]', NULL, datetime('now'));
