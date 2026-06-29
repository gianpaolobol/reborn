CREATE TABLE IF NOT EXISTS api_rate_limits (
    id TEXT PRIMARY KEY,
    rate_key TEXT NOT NULL,
    route TEXT NOT NULL,
    window_start INTEGER NOT NULL,
    request_count INTEGER NOT NULL,
    last_seen_at TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_api_rate_limits_key_route_window
ON api_rate_limits(rate_key, route, window_start);

CREATE INDEX IF NOT EXISTS idx_api_rate_limits_last_seen
ON api_rate_limits(last_seen_at);

CREATE TABLE IF NOT EXISTS platform_readiness_snapshots (
    id TEXT PRIMARY KEY,
    status TEXT NOT NULL,
    checks_json TEXT NOT NULL,
    created_by TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_audit_log (
    id TEXT PRIMARY KEY,
    actor_id TEXT NULL,
    action TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT NULL,
    metadata_json TEXT NOT NULL,
    request_id TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_audit_log_action_created
ON platform_audit_log(action, created_at);
