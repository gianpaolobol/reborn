CREATE TABLE IF NOT EXISTS repair_attachments (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    original_filename TEXT NOT NULL,
    stored_path TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    size_bytes INTEGER NOT NULL DEFAULT 0,
    sha256 TEXT NOT NULL,
    kind TEXT NOT NULL DEFAULT 'repair_asset',
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_repair_attachments_case ON repair_attachments(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_repair_attachments_sha256 ON repair_attachments(sha256);

CREATE TABLE IF NOT EXISTS audit_log (
    id TEXT PRIMARY KEY,
    actor_id TEXT NULL,
    action TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT NOT NULL,
    context TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_audit_log_subject ON audit_log(subject_type, subject_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log(action);
