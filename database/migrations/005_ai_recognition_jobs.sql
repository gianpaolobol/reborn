CREATE TABLE IF NOT EXISTS recognition_jobs (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    requested_by TEXT NOT NULL,
    status TEXT NOT NULL,
    input_attachment_ids TEXT NOT NULL,
    result_json TEXT NULL,
    error_message TEXT NULL,
    created_at TEXT NOT NULL,
    started_at TEXT NULL,
    completed_at TEXT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_recognition_jobs_case ON recognition_jobs(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_recognition_jobs_status ON recognition_jobs(status);
CREATE INDEX IF NOT EXISTS idx_recognition_jobs_requested_by ON recognition_jobs(requested_by);
CREATE INDEX IF NOT EXISTS idx_recognition_jobs_created_at ON recognition_jobs(created_at);
