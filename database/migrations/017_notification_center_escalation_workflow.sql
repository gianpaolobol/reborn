CREATE TABLE IF NOT EXISTS platform_notification_channels (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    target TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    config_json TEXT NOT NULL DEFAULT '{}',
    last_used_at TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_notification_channels_status
ON platform_notification_channels(status, type);

CREATE TABLE IF NOT EXISTS platform_notification_rules (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    trigger_type TEXT NOT NULL,
    min_severity TEXT NOT NULL DEFAULT 'low',
    channel_id TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    template_subject TEXT NOT NULL,
    template_body TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (channel_id) REFERENCES platform_notification_channels(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_notification_rules_trigger
ON platform_notification_rules(trigger_type, enabled);

CREATE TABLE IF NOT EXISTS platform_notification_deliveries (
    id TEXT PRIMARY KEY,
    channel_id TEXT NOT NULL,
    rule_id TEXT NULL,
    target_type TEXT NOT NULL,
    target_id TEXT NULL,
    severity TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT NOT NULL,
    transport TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    error_message TEXT NULL,
    dispatched_by TEXT NULL,
    dispatched_at TEXT NOT NULL,
    sent_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (channel_id) REFERENCES platform_notification_channels(id),
    FOREIGN KEY (rule_id) REFERENCES platform_notification_rules(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_notification_deliveries_status_time
ON platform_notification_deliveries(status, dispatched_at);

CREATE INDEX IF NOT EXISTS idx_platform_notification_deliveries_target
ON platform_notification_deliveries(target_type, target_id);

CREATE TABLE IF NOT EXISTS platform_escalation_policies (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    severity TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    steps_json TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_escalation_policies_enabled
ON platform_escalation_policies(enabled, severity);

CREATE TABLE IF NOT EXISTS platform_escalation_runs (
    id TEXT PRIMARY KEY,
    policy_id TEXT NOT NULL,
    incident_id TEXT NOT NULL,
    status TEXT NOT NULL,
    current_step INTEGER NOT NULL DEFAULT 1,
    summary TEXT NOT NULL,
    context_json TEXT NOT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    completed_at TEXT NULL,
    FOREIGN KEY (policy_id) REFERENCES platform_escalation_policies(id),
    FOREIGN KEY (incident_id) REFERENCES platform_incidents(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_escalation_runs_status_time
ON platform_escalation_runs(status, created_at);

INSERT OR IGNORE INTO platform_notification_channels (id, name, type, target, status, config_json, created_by, created_at, updated_at)
VALUES
('channel-local-ops-console', 'Local Ops Console', 'in_app', 'admin_console', 'active', '{"mock":true,"description":"Stores operator notifications inside SQLite for local/pilot use."}', NULL, datetime('now'), datetime('now')),
('channel-demo-ops-email', 'Demo Ops Email', 'email', 'ops@reborn.local', 'active', '{"mock":true,"description":"Mock email channel. Does not send external email."}', NULL, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_notification_rules (id, name, trigger_type, min_severity, channel_id, enabled, template_subject, template_body, created_at, updated_at)
VALUES
('rule-notify-critical-alert', 'Notify critical/high alerts', 'alert', 'high', 'channel-local-ops-console', 1, '[Re-born] {{severity}} alert: {{title}}', '{{summary}}\nTarget: {{target_type}}/{{target_id}}', datetime('now'), datetime('now')),
('rule-notify-active-incident', 'Notify active incidents', 'incident', 'medium', 'channel-local-ops-console', 1, '[Re-born] Incident: {{title}}', '{{summary}}\nSeverity: {{severity}}', datetime('now'), datetime('now')),
('rule-notify-status-update', 'Notify status updates', 'status_update', 'low', 'channel-local-ops-console', 1, '[Re-born] Status update: {{title}}', '{{summary}}', datetime('now'), datetime('now')),
('rule-notify-maintenance', 'Notify maintenance windows', 'maintenance', 'low', 'channel-demo-ops-email', 1, '[Re-born] Maintenance: {{title}}', '{{summary}}', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_escalation_policies (id, name, severity, enabled, steps_json, created_at, updated_at)
VALUES
('policy-critical-incident', 'Critical incident escalation', 'critical', 1, '[{"step":1,"after_minutes":0,"action":"notify_ops_console"},{"step":2,"after_minutes":15,"action":"notify_founder"},{"step":3,"after_minutes":30,"action":"freeze_demo_or_pilot"}]', datetime('now'), datetime('now')),
('policy-high-incident', 'High severity incident escalation', 'high', 1, '[{"step":1,"after_minutes":0,"action":"notify_ops_console"},{"step":2,"after_minutes":30,"action":"post_status_update"}]', datetime('now'), datetime('now')),
('policy-medium-incident', 'Medium incident escalation', 'medium', 1, '[{"step":1,"after_minutes":0,"action":"notify_ops_console"},{"step":2,"after_minutes":60,"action":"review_before_demo"}]', datetime('now'), datetime('now')),
('policy-low-incident', 'Low severity incident note', 'low', 1, '[{"step":1,"after_minutes":0,"action":"record_ops_note"}]', datetime('now'), datetime('now'));
