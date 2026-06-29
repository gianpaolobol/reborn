CREATE TABLE IF NOT EXISTS provider_trust_reviews (
    id TEXT PRIMARY KEY,
    completion_report_id TEXT NOT NULL,
    fulfilment_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    reviewer_id TEXT NOT NULL,
    reviewer_role TEXT NOT NULL,
    status TEXT NOT NULL,
    rating_overall INTEGER NOT NULL,
    rating_quality INTEGER NOT NULL,
    rating_communication INTEGER NOT NULL,
    rating_timeliness INTEGER NOT NULL,
    would_recommend INTEGER NOT NULL DEFAULT 1,
    issue_resolved INTEGER NOT NULL DEFAULT 1,
    comment TEXT NULL,
    signals_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (completion_report_id) REFERENCES repair_completion_reports(id),
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_provider_trust_reviews_report_reviewer ON provider_trust_reviews(completion_report_id, reviewer_id);
CREATE INDEX IF NOT EXISTS idx_provider_trust_reviews_provider ON provider_trust_reviews(provider_id);
CREATE INDEX IF NOT EXISTS idx_provider_trust_reviews_case ON provider_trust_reviews(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_provider_trust_reviews_status ON provider_trust_reviews(status);

CREATE TABLE IF NOT EXISTS provider_trust_signals (
    id TEXT PRIMARY KEY,
    provider_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    completion_report_id TEXT NOT NULL,
    trust_review_id TEXT NOT NULL,
    event_type TEXT NOT NULL,
    signal_json TEXT NOT NULL DEFAULT '{}',
    score_delta REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (completion_report_id) REFERENCES repair_completion_reports(id),
    FOREIGN KEY (trust_review_id) REFERENCES provider_trust_reviews(id)
);

CREATE INDEX IF NOT EXISTS idx_provider_trust_signals_provider ON provider_trust_signals(provider_id);
CREATE INDEX IF NOT EXISTS idx_provider_trust_signals_case ON provider_trust_signals(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_provider_trust_signals_report ON provider_trust_signals(completion_report_id);

CREATE TABLE IF NOT EXISTS provider_quality_scores (
    provider_id TEXT PRIMARY KEY,
    review_count INTEGER NOT NULL DEFAULT 0,
    completed_repairs_count INTEGER NOT NULL DEFAULT 0,
    successful_repairs_count INTEGER NOT NULL DEFAULT 0,
    average_rating REAL NOT NULL DEFAULT 0,
    quality_score REAL NOT NULL DEFAULT 0,
    reliability_score REAL NOT NULL DEFAULT 0,
    communication_score REAL NOT NULL DEFAULT 0,
    timeliness_score REAL NOT NULL DEFAULT 0,
    overall_score REAL NOT NULL DEFAULT 0,
    trust_tier TEXT NOT NULL DEFAULT 'unrated',
    last_review_id TEXT NULL,
    score_json TEXT NOT NULL DEFAULT '{}',
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_provider_quality_scores_overall ON provider_quality_scores(overall_score);
CREATE INDEX IF NOT EXISTS idx_provider_quality_scores_tier ON provider_quality_scores(trust_tier);
