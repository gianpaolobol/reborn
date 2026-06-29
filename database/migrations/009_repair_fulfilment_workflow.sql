CREATE TABLE IF NOT EXISTS repair_fulfilments (
    id TEXT PRIMARY KEY,
    repair_order_id TEXT NOT NULL,
    quote_request_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    requested_by TEXT NOT NULL,
    accepted_by TEXT NULL,
    status TEXT NOT NULL,
    provider_notes TEXT NULL,
    tracking_reference TEXT NULL,
    timeline_json TEXT NOT NULL,
    created_at TEXT NOT NULL,
    accepted_at TEXT NULL,
    started_at TEXT NULL,
    quality_checked_at TEXT NULL,
    ready_at TEXT NULL,
    completed_at TEXT NULL,
    rejected_at TEXT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (repair_order_id) REFERENCES repair_orders(id),
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id)
);

CREATE INDEX IF NOT EXISTS idx_repair_fulfilments_order ON repair_fulfilments(repair_order_id);
CREATE INDEX IF NOT EXISTS idx_repair_fulfilments_case ON repair_fulfilments(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_repair_fulfilments_provider ON repair_fulfilments(provider_id);
CREATE INDEX IF NOT EXISTS idx_repair_fulfilments_status ON repair_fulfilments(status);
