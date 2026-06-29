CREATE TABLE IF NOT EXISTS repair_path_decisions (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    recognition_job_id TEXT NULL,
    requested_by TEXT NOT NULL,
    status TEXT NOT NULL,
    result_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    completed_at TEXT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (recognition_job_id) REFERENCES recognition_jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_repair_path_decisions_case ON repair_path_decisions(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_repair_path_decisions_recognition_job ON repair_path_decisions(recognition_job_id);
CREATE INDEX IF NOT EXISTS idx_repair_path_decisions_status ON repair_path_decisions(status);
CREATE INDEX IF NOT EXISTS idx_repair_path_decisions_created_at ON repair_path_decisions(created_at);
