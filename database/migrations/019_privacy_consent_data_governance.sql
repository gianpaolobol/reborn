CREATE TABLE IF NOT EXISTS platform_privacy_notices (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    scope TEXT NOT NULL,
    version TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    effective_from TEXT,
    review_due_at TEXT,
    summary TEXT,
    policy_text TEXT,
    rights_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS platform_consent_records (
    id TEXT PRIMARY KEY,
    user_id TEXT,
    subject_email TEXT,
    notice_id TEXT NOT NULL,
    consent_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'granted',
    source TEXT NOT NULL DEFAULT 'admin_console',
    metadata_json TEXT NOT NULL DEFAULT '{}',
    granted_at TEXT,
    withdrawn_at TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notice_id) REFERENCES platform_privacy_notices(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_consent_records_subject ON platform_consent_records(subject_email, user_id);
CREATE INDEX IF NOT EXISTS idx_platform_consent_records_status ON platform_consent_records(status, consent_type);

CREATE TABLE IF NOT EXISTS platform_data_processing_records (
    id TEXT PRIMARY KEY,
    activity_code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    domain TEXT NOT NULL,
    data_categories_json TEXT NOT NULL DEFAULT '[]',
    purpose TEXT NOT NULL,
    lawful_basis TEXT NOT NULL,
    retention_days INTEGER NOT NULL DEFAULT 365,
    processors_json TEXT NOT NULL DEFAULT '[]',
    risk_level TEXT NOT NULL DEFAULT 'medium',
    status TEXT NOT NULL DEFAULT 'draft',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS platform_retention_rules (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    scope TEXT NOT NULL,
    table_name TEXT NOT NULL,
    data_category TEXT NOT NULL,
    retention_days INTEGER NOT NULL,
    action TEXT NOT NULL DEFAULT 'review',
    enabled INTEGER NOT NULL DEFAULT 1,
    description TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS platform_retention_evaluations (
    id TEXT PRIMARY KEY,
    rule_id TEXT NOT NULL,
    candidate_count INTEGER NOT NULL DEFAULT 0,
    oldest_record_at TEXT,
    status TEXT NOT NULL DEFAULT 'ok',
    summary_json TEXT NOT NULL DEFAULT '{}',
    evaluated_by TEXT,
    evaluated_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES platform_retention_rules(id)
);

CREATE TABLE IF NOT EXISTS platform_data_subject_requests (
    id TEXT PRIMARY KEY,
    request_type TEXT NOT NULL,
    subject_email TEXT NOT NULL,
    subject_user_id TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'normal',
    description TEXT,
    response_due_at TEXT NOT NULL,
    resolved_at TEXT,
    resolution_notes TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_platform_dsr_status ON platform_data_subject_requests(status, response_due_at);
CREATE INDEX IF NOT EXISTS idx_platform_dsr_subject ON platform_data_subject_requests(subject_email, subject_user_id);

CREATE TABLE IF NOT EXISTS platform_data_exports (
    id TEXT PRIMARY KEY,
    request_id TEXT NOT NULL,
    subject_email TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'generated',
    payload_json TEXT NOT NULL DEFAULT '{}',
    generated_by TEXT,
    generated_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES platform_data_subject_requests(id)
);

INSERT OR IGNORE INTO platform_privacy_notices (id, code, title, scope, version, status, effective_from, review_due_at, summary, policy_text, rights_json, created_at, updated_at) VALUES
('privacy-notice-repair-intake-v1', 'REPAIR-INTAKE-PRIVACY', 'Repair intake privacy notice', 'repair_journey', '0.1', 'draft', datetime('now'), datetime('now', '+30 days'), 'Explains how repair photos, descriptions and uploaded files are used during pilot repair intake.', 'Pilot mode: uploaded repair data is stored locally, used for mock recognition/decision flows and must not be shared with real external AI providers until a production privacy review is completed.', '["access", "rectification", "erasure", "restriction", "portability"]', datetime('now'), datetime('now')),
('privacy-notice-provider-v1', 'PROVIDER-DATA-PRIVACY', 'Provider data privacy notice', 'provider_network', '0.1', 'draft', datetime('now'), datetime('now', '+45 days'), 'Explains how provider profile, ranking, trust and operational data are used in marketplace governance.', 'Pilot mode: provider quality, ranking and SLA data are local governance records and are not legal performance assessments until provider contracts are approved.', '["access", "rectification", "objection", "restriction"]', datetime('now'), datetime('now')),
('privacy-notice-ops-v1', 'OPS-OBSERVABILITY-PRIVACY', 'Operations and observability privacy notice', 'platform_operations', '0.1', 'draft', datetime('now'), datetime('now', '+21 days'), 'Explains how logs, API metrics, alerting and incident records are stored for platform safety.', 'Pilot mode: operational telemetry is stored locally to diagnose system reliability and must avoid unnecessary personal data.', '["access", "erasure", "restriction"]', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_data_processing_records (id, activity_code, name, domain, data_categories_json, purpose, lawful_basis, retention_days, processors_json, risk_level, status, created_at, updated_at) VALUES
('dpr-repair-intake', 'DPR-REPAIR-INTAKE', 'Repair case intake and uploaded diagnostics', 'repair', '["account", "repair_description", "photos", "uploaded_files", "dimensions"]', 'Create repair cases, support AI-assisted diagnosis and generate repair path options.', 'contract_or_pre_contractual_request', 365, '["local_sqlite", "local_file_storage"]', 'high', 'draft', datetime('now'), datetime('now')),
('dpr-ai-learning', 'DPR-AI-LEARNING', 'AI recognition and repair learning feedback', 'ai_learning', '["repair_outcomes", "recognition_results", "knowledge_graph_feedback"]', 'Improve repair recommendations, knowledge graph confidence and future repair journeys.', 'legitimate_interest_with_review', 730, '["local_sqlite"]', 'medium', 'draft', datetime('now'), datetime('now')),
('dpr-provider-governance', 'DPR-PROVIDER-GOVERNANCE', 'Provider matching, trust and governance scoring', 'marketplace', '["provider_profile", "quote_requests", "quality_scores", "trust_reviews", "governance_actions"]', 'Rank providers, support quote workflows and monitor marketplace quality.', 'contract_or_legitimate_interest', 730, '["local_sqlite"]', 'medium', 'draft', datetime('now'), datetime('now')),
('dpr-platform-ops', 'DPR-PLATFORM-OPS', 'Platform observability, incidents and notifications', 'platform', '["http_metrics", "logs", "alerts", "incidents", "notifications", "sla_evaluations"]', 'Operate Re-born safely during local/pilot validation and support incident response.', 'legitimate_interest_security', 180, '["local_sqlite", "local_log_files"]', 'medium', 'draft', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_retention_rules (id, code, scope, table_name, data_category, retention_days, action, enabled, description, created_at, updated_at) VALUES
('retention-http-metrics', 'RETENTION-HTTP-METRICS-180D', 'platform', 'platform_http_metrics', 'operational_metrics', 180, 'review', 1, 'Review HTTP metrics older than 180 days before pilot production.', datetime('now'), datetime('now')),
('retention-readiness-snapshots', 'RETENTION-READINESS-365D', 'platform', 'platform_readiness_snapshots', 'readiness_audit', 365, 'keep', 1, 'Keep readiness snapshots as deploy evidence for one year in pilot mode.', datetime('now'), datetime('now')),
('retention-repair-cases', 'RETENTION-REPAIR-CASES-365D', 'repair', 'repair_cases', 'repair_case_data', 365, 'review', 1, 'Review repair case records older than one year before moving beyond pilot.', datetime('now'), datetime('now')),
('retention-uploads', 'RETENTION-REPAIR-UPLOADS-180D', 'repair', 'repair_attachments', 'repair_upload_metadata', 180, 'review', 1, 'Review repair attachment metadata older than 180 days; file deletion requires storage-aware cleanup.', datetime('now'), datetime('now')),
('retention-notifications', 'RETENTION-NOTIFICATIONS-180D', 'platform', 'platform_notification_deliveries', 'notification_delivery_records', 180, 'review', 1, 'Review mock notification delivery records older than 180 days.', datetime('now'), datetime('now'));
