CREATE TABLE IF NOT EXISTS repair_completion_reports (
    id TEXT PRIMARY KEY,
    fulfilment_id TEXT NOT NULL,
    repair_order_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    reported_by TEXT NOT NULL,
    status TEXT NOT NULL,
    outcome_status TEXT NOT NULL,
    functional_result TEXT NOT NULL,
    customer_confirmed INTEGER NOT NULL DEFAULT 0,
    object_saved INTEGER NOT NULL DEFAULT 1,
    co2_avoided_grams INTEGER NOT NULL DEFAULT 0,
    evidence_attachment_ids TEXT NOT NULL DEFAULT '[]',
    outcome_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (fulfilment_id) REFERENCES repair_fulfilments(id),
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id)
);

CREATE INDEX IF NOT EXISTS idx_repair_completion_reports_fulfilment ON repair_completion_reports(fulfilment_id);
CREATE INDEX IF NOT EXISTS idx_repair_completion_reports_case ON repair_completion_reports(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_repair_completion_reports_provider ON repair_completion_reports(provider_id);
CREATE INDEX IF NOT EXISTS idx_repair_completion_reports_status ON repair_completion_reports(status);

CREATE TABLE IF NOT EXISTS repair_learning_events (
    id TEXT PRIMARY KEY,
    completion_report_id TEXT NOT NULL,
    fulfilment_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    event_type TEXT NOT NULL,
    signal_json TEXT NOT NULL DEFAULT '{}',
    confidence_delta REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (completion_report_id) REFERENCES repair_completion_reports(id),
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id)
);

CREATE INDEX IF NOT EXISTS idx_repair_learning_events_report ON repair_learning_events(completion_report_id);
CREATE INDEX IF NOT EXISTS idx_repair_learning_events_case ON repair_learning_events(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_repair_learning_events_provider ON repair_learning_events(provider_id);
CREATE INDEX IF NOT EXISTS idx_repair_learning_events_type ON repair_learning_events(event_type);
