CREATE TABLE IF NOT EXISTS provider_matches (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    repair_path_decision_id TEXT NULL,
    requested_by TEXT NOT NULL,
    status TEXT NOT NULL,
    result_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    completed_at TEXT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (repair_path_decision_id) REFERENCES repair_path_decisions(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_provider_matches_case ON provider_matches(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_provider_matches_decision ON provider_matches(repair_path_decision_id);
CREATE INDEX IF NOT EXISTS idx_provider_matches_status ON provider_matches(status);
CREATE INDEX IF NOT EXISTS idx_provider_matches_created_at ON provider_matches(created_at);

CREATE TABLE IF NOT EXISTS provider_quote_requests (
    id TEXT PRIMARY KEY,
    provider_match_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    requested_by TEXT NOT NULL,
    status TEXT NOT NULL,
    quote_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    accepted_at TEXT NULL,
    FOREIGN KEY (provider_match_id) REFERENCES provider_matches(id) ON DELETE CASCADE,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_provider_quote_requests_match ON provider_quote_requests(provider_match_id);
CREATE INDEX IF NOT EXISTS idx_provider_quote_requests_case ON provider_quote_requests(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_provider_quote_requests_provider ON provider_quote_requests(provider_id);
CREATE INDEX IF NOT EXISTS idx_provider_quote_requests_status ON provider_quote_requests(status);
CREATE INDEX IF NOT EXISTS idx_provider_quote_requests_created_at ON provider_quote_requests(created_at);
