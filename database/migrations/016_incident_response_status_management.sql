CREATE TABLE IF NOT EXISTS platform_alert_rules (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    metric TEXT NOT NULL,
    comparator TEXT NOT NULL,
    threshold_value REAL NOT NULL,
    severity TEXT NOT NULL,
    window_minutes INTEGER NOT NULL DEFAULT 15,
    enabled INTEGER NOT NULL DEFAULT 1,
    description TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_alert_rules_enabled
ON platform_alert_rules(enabled);

CREATE TABLE IF NOT EXISTS platform_alerts (
    id TEXT PRIMARY KEY,
    rule_id TEXT NULL,
    name TEXT NOT NULL,
    severity TEXT NOT NULL,
    status TEXT NOT NULL,
    metric TEXT NOT NULL,
    metric_value REAL NOT NULL,
    threshold_value REAL NOT NULL,
    message TEXT NOT NULL,
    context_json TEXT NOT NULL,
    opened_at TEXT NOT NULL,
    acknowledged_at TEXT NULL,
    acknowledged_by TEXT NULL,
    resolved_at TEXT NULL,
    resolved_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (rule_id) REFERENCES platform_alert_rules(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_alerts_status_opened
ON platform_alerts(status, opened_at);

CREATE INDEX IF NOT EXISTS idx_platform_alerts_rule_status
ON platform_alerts(rule_id, status);

CREATE TABLE IF NOT EXISTS platform_incidents (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    severity TEXT NOT NULL,
    status TEXT NOT NULL,
    summary TEXT NOT NULL,
    impact TEXT NULL,
    linked_alert_id TEXT NULL,
    opened_by TEXT NULL,
    assigned_to TEXT NULL,
    opened_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    resolved_at TEXT NULL,
    FOREIGN KEY (linked_alert_id) REFERENCES platform_alerts(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_incidents_status_opened
ON platform_incidents(status, opened_at);

CREATE TABLE IF NOT EXISTS platform_status_updates (
    id TEXT PRIMARY KEY,
    incident_id TEXT NULL,
    component TEXT NOT NULL,
    status TEXT NOT NULL,
    message TEXT NOT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (incident_id) REFERENCES platform_incidents(id)
);

CREATE INDEX IF NOT EXISTS idx_platform_status_updates_created
ON platform_status_updates(created_at);

CREATE TABLE IF NOT EXISTS platform_maintenance_windows (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    status TEXT NOT NULL,
    starts_at TEXT NOT NULL,
    ends_at TEXT NOT NULL,
    reason TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_maintenance_windows_status_time
ON platform_maintenance_windows(status, starts_at, ends_at);

INSERT OR IGNORE INTO platform_alert_rules (id, name, metric, comparator, threshold_value, severity, window_minutes, enabled, description, created_at, updated_at)
VALUES
('rule-readiness-not-ready', 'Readiness is not acceptable', 'readiness_not_ready', '>=', 1, 'critical', 5, 1, 'Triggers when /api/ready returns not_ready instead of ready or degraded.', datetime('now'), datetime('now')),
('rule-http-5xx', 'Repeated API 5xx responses', 'http_5xx_count', '>=', 1, 'high', 15, 1, 'Triggers when the API records at least one 5xx response in the last window.', datetime('now'), datetime('now')),
('rule-http-latency', 'Slow API average response', 'http_avg_duration_ms', '>', 1500, 'medium', 15, 1, 'Triggers when average API duration crosses the pilot threshold.', datetime('now'), datetime('now')),
('rule-backup-missing', 'SQLite backup is missing or stale', 'backup_age_hours', '>', 24, 'medium', 1440, 1, 'Triggers when no completed backup exists or the latest backup is older than one day.', datetime('now'), datetime('now'));
