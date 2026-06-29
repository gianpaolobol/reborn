CREATE TABLE IF NOT EXISTS ops_review_items (
    id TEXT PRIMARY KEY,
    source_type TEXT NOT NULL,
    source_id TEXT NOT NULL,
    repair_case_id TEXT NULL,
    provider_id TEXT NULL,
    category TEXT NOT NULL,
    priority TEXT NOT NULL,
    status TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    assigned_to TEXT NULL,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    resolved_at TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_ops_review_items_status ON ops_review_items(status);
CREATE INDEX IF NOT EXISTS idx_ops_review_items_priority ON ops_review_items(priority);
CREATE INDEX IF NOT EXISTS idx_ops_review_items_provider ON ops_review_items(provider_id);
CREATE INDEX IF NOT EXISTS idx_ops_review_items_case ON ops_review_items(repair_case_id);

CREATE TABLE IF NOT EXISTS ops_moderation_actions (
    id TEXT PRIMARY KEY,
    review_item_id TEXT NOT NULL,
    action_type TEXT NOT NULL,
    target_type TEXT NOT NULL,
    target_id TEXT NOT NULL,
    status TEXT NOT NULL,
    reason TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (review_item_id) REFERENCES ops_review_items(id)
);

CREATE INDEX IF NOT EXISTS idx_ops_moderation_actions_review ON ops_moderation_actions(review_item_id);
CREATE INDEX IF NOT EXISTS idx_ops_moderation_actions_target ON ops_moderation_actions(target_type, target_id);

CREATE TABLE IF NOT EXISTS ops_escalations (
    id TEXT PRIMARY KEY,
    review_item_id TEXT NOT NULL,
    escalation_level TEXT NOT NULL,
    status TEXT NOT NULL,
    reason TEXT NOT NULL,
    assigned_to TEXT NULL,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    resolved_at TEXT NULL,
    FOREIGN KEY (review_item_id) REFERENCES ops_review_items(id)
);

CREATE INDEX IF NOT EXISTS idx_ops_escalations_status ON ops_escalations(status);
CREATE INDEX IF NOT EXISTS idx_ops_escalations_review ON ops_escalations(review_item_id);

CREATE TABLE IF NOT EXISTS ops_audit_log (
    id TEXT PRIMARY KEY,
    actor_id TEXT NOT NULL,
    action TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_ops_audit_log_subject ON ops_audit_log(subject_type, subject_id);
CREATE INDEX IF NOT EXISTS idx_ops_audit_log_created_at ON ops_audit_log(created_at);
