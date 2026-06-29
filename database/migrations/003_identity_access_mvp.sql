ALTER TABLE users ADD COLUMN password_hash TEXT NULL;
ALTER TABLE users ADD COLUMN status TEXT NOT NULL DEFAULT 'active';
ALTER TABLE users ADD COLUMN email_verified_at TEXT NULL;
ALTER TABLE users ADD COLUMN updated_at TEXT NULL;
ALTER TABLE users ADD COLUMN last_login_at TEXT NULL;

ALTER TABLE repair_cases ADD COLUMN owner_id TEXT NULL;
CREATE INDEX IF NOT EXISTS idx_repair_cases_owner ON repair_cases(owner_id);

CREATE TABLE IF NOT EXISTS auth_sessions (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL DEFAULT 'api_session',
    abilities TEXT NOT NULL DEFAULT '[]',
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    expires_at TEXT NOT NULL,
    revoked_at TEXT NULL,
    created_at TEXT NOT NULL,
    last_used_at TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_auth_sessions_user ON auth_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_auth_sessions_token_hash ON auth_sessions(token_hash);
CREATE INDEX IF NOT EXISTS idx_auth_sessions_expires_at ON auth_sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_audit_log_actor ON audit_log(actor_id);
