CREATE TABLE IF NOT EXISTS minimal_live_recognition_outputs (
    id TEXT PRIMARY KEY,
    recognition_job_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    input_attachment_ids TEXT NOT NULL DEFAULT '[]',
    recognized_identifier TEXT NOT NULL DEFAULT 'id non riconosciuta',
    recognition_status TEXT NOT NULL DEFAULT 'unrecognized',
    output_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    FOREIGN KEY (recognition_job_id) REFERENCES recognition_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_minimal_live_recognition_outputs_job ON minimal_live_recognition_outputs(recognition_job_id);
CREATE INDEX IF NOT EXISTS idx_minimal_live_recognition_outputs_case ON minimal_live_recognition_outputs(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_minimal_live_recognition_outputs_status ON minimal_live_recognition_outputs(recognition_status);
