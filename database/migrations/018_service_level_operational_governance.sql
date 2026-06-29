CREATE TABLE IF NOT EXISTS platform_sla_policies (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    source_type TEXT NOT NULL,
    severity TEXT NOT NULL,
    response_minutes INTEGER NOT NULL,
    resolution_minutes INTEGER NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    description TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_sla_policies_enabled
ON platform_sla_policies(enabled, source_type, severity);

CREATE TABLE IF NOT EXISTS platform_sla_evaluations (
    id TEXT PRIMARY KEY,
    source_type TEXT NOT NULL,
    source_id TEXT NOT NULL,
    policy_id TEXT NOT NULL,
    severity TEXT NOT NULL,
    status TEXT NOT NULL,
    response_due_at TEXT NOT NULL,
    resolution_due_at TEXT NOT NULL,
    first_response_at TEXT NULL,
    resolved_at TEXT NULL,
    response_breached INTEGER NOT NULL DEFAULT 0,
    resolution_breached INTEGER NOT NULL DEFAULT 0,
    context_json TEXT NOT NULL,
    evaluated_by TEXT NULL,
    evaluated_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (policy_id) REFERENCES platform_sla_policies(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_platform_sla_evaluations_source
ON platform_sla_evaluations(source_type, source_id, policy_id);

CREATE INDEX IF NOT EXISTS idx_platform_sla_evaluations_status_due
ON platform_sla_evaluations(status, resolution_due_at);

CREATE TABLE IF NOT EXISTS platform_operational_policies (
    id TEXT PRIMARY KEY,
    policy_code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    scope TEXT NOT NULL,
    status TEXT NOT NULL,
    version TEXT NOT NULL,
    owner_role TEXT NOT NULL,
    review_due_at TEXT NULL,
    summary TEXT NOT NULL,
    requirements_json TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_operational_policies_status
ON platform_operational_policies(status, scope);

CREATE TABLE IF NOT EXISTS platform_policy_attestations (
    id TEXT PRIMARY KEY,
    policy_id TEXT NOT NULL,
    status TEXT NOT NULL,
    notes TEXT NULL,
    attested_by TEXT NULL,
    attested_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (policy_id) REFERENCES platform_operational_policies(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_policy_attestations_policy_time
ON platform_policy_attestations(policy_id, attested_at);

INSERT OR IGNORE INTO platform_sla_policies (id, name, source_type, severity, response_minutes, resolution_minutes, enabled, description, created_at, updated_at)
VALUES
('sla-alert-critical', 'Critical alert response SLA', 'alert', 'critical', 5, 60, 1, 'Critical alerts must be acknowledged quickly during pilot/beta operations.', datetime('now'), datetime('now')),
('sla-alert-high', 'High alert response SLA', 'alert', 'high', 15, 180, 1, 'High severity alerts must be acknowledged and cleared before demo or pilot continuation.', datetime('now'), datetime('now')),
('sla-alert-medium', 'Medium alert response SLA', 'alert', 'medium', 60, 1440, 1, 'Medium alerts are managed inside daily operations.', datetime('now'), datetime('now')),
('sla-incident-critical', 'Critical incident SLA', 'incident', 'critical', 10, 240, 1, 'Critical incidents require immediate operator response and same-day resolution target.', datetime('now'), datetime('now')),
('sla-incident-high', 'High incident SLA', 'incident', 'high', 30, 480, 1, 'High incidents must have a documented response and resolution path before beta usage continues.', datetime('now'), datetime('now')),
('sla-incident-medium', 'Medium incident SLA', 'incident', 'medium', 120, 2880, 1, 'Medium incidents are tracked in the pilot operating rhythm.', datetime('now'), datetime('now')),
('sla-incident-low', 'Low incident SLA', 'incident', 'low', 1440, 10080, 1, 'Low severity operational issues are documented and reviewed weekly.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_operational_policies (id, policy_code, title, scope, status, version, owner_role, review_due_at, summary, requirements_json, created_at, updated_at)
VALUES
('policy-pilot-readiness-gate', 'PILOT-READINESS-GATE', 'Pilot readiness gate', 'platform', 'active', '1.0', 'admin', datetime('now', '+30 days'), 'Defines the minimum readiness, backup, incident and smoke-test evidence required before a pilot/demo.', '["/api/ready must be ready or degraded", "production readiness smoke test must pass", "latest backup must be recent", "open critical incidents must be zero", "status page must be reviewed before demo"]', datetime('now'), datetime('now')),
('policy-incident-comms', 'INCIDENT-COMMS', 'Incident communication policy', 'operations', 'active', '1.0', 'admin', datetime('now', '+45 days'), 'Defines how incidents are acknowledged, updated and communicated in the local/pilot operating model.', '["all high/critical incidents need a status update", "operator must acknowledge alert before resolving", "public status output must not expose private user data", "notification deliveries remain mock until transport integrations are approved"]', datetime('now'), datetime('now')),
('policy-backup-restore', 'BACKUP-RESTORE', 'Backup and restore policy', 'platform', 'draft', '0.1', 'admin', datetime('now', '+14 days'), 'Documents backup cadence and restore verification needed before production deployment.', '["manual backup before migrations", "restore checklist must be tested", "storage/backups must not be committed", "backup freshness is part of operational readiness"]', datetime('now'), datetime('now')),
('policy-provider-quality', 'PROVIDER-QUALITY-GOVERNANCE', 'Provider quality governance policy', 'marketplace', 'draft', '0.1', 'admin', datetime('now', '+60 days'), 'Connects trust score, governance actions and SLA breaches to provider visibility and pilot participation.', '["provider quality score informs ranking", "governance action required for repeated quality issues", "human review before suspension", "repair outcome feedback must feed learning and trust"]', datetime('now'), datetime('now')),
('policy-upload-data', 'UPLOAD-DATA-HANDLING', 'Repair upload data handling policy', 'privacy', 'draft', '0.1', 'admin', datetime('now', '+21 days'), 'Defines how repair photos/files are handled in pilot mode before real privacy/legal approval.', '["uploaded files stored locally in development", "no sensitive personal data in demo uploads", "production retention rules still required", "AI provider data-sharing must be documented before real integrations"]', datetime('now'), datetime('now'));
