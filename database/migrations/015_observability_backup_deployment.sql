CREATE TABLE IF NOT EXISTS platform_http_metrics (
    id TEXT PRIMARY KEY,
    request_id TEXT NULL,
    method TEXT NOT NULL,
    path TEXT NOT NULL,
    status_code INTEGER NOT NULL,
    duration_ms INTEGER NOT NULL,
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    occurred_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_http_metrics_occurred_at
ON platform_http_metrics(occurred_at);

CREATE INDEX IF NOT EXISTS idx_platform_http_metrics_path_occurred
ON platform_http_metrics(path, occurred_at);

CREATE INDEX IF NOT EXISTS idx_platform_http_metrics_status_occurred
ON platform_http_metrics(status_code, occurred_at);

CREATE TABLE IF NOT EXISTS platform_backup_runs (
    id TEXT PRIMARY KEY,
    backup_file TEXT NOT NULL,
    status TEXT NOT NULL,
    size_bytes INTEGER NOT NULL DEFAULT 0,
    database_size_bytes INTEGER NOT NULL DEFAULT 0,
    triggered_by TEXT NULL,
    triggered_via TEXT NOT NULL,
    error_message TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_backup_runs_created_at
ON platform_backup_runs(created_at);

CREATE INDEX IF NOT EXISTS idx_platform_backup_runs_status
ON platform_backup_runs(status);

CREATE TABLE IF NOT EXISTS platform_deployment_checks (
    id TEXT PRIMARY KEY,
    environment TEXT NOT NULL,
    status TEXT NOT NULL,
    checklist_json TEXT NOT NULL,
    notes TEXT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_deployment_checks_env_created
ON platform_deployment_checks(environment, created_at);
