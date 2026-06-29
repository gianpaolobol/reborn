CREATE TABLE IF NOT EXISTS platform_customer_acceptance_policies (
    id TEXT PRIMARY KEY,
    policy_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    priority INTEGER NOT NULL DEFAULT 100,
    description TEXT NOT NULL,
    rules_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_customer_acceptance_records (
    id TEXT PRIMARY KEY,
    acceptance_code TEXT NOT NULL UNIQUE,
    proof_of_repair_id TEXT,
    dispatch_id TEXT,
    repair_case_id TEXT,
    repair_order_id TEXT,
    customer_user_id TEXT,
    customer_email TEXT,
    status TEXT NOT NULL DEFAULT 'pending_acceptance',
    acceptance_decision TEXT,
    satisfaction_score INTEGER,
    issue_summary TEXT,
    evidence_json TEXT NOT NULL DEFAULT '{}',
    requested_at TEXT NOT NULL,
    decided_at TEXT,
    due_at TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (proof_of_repair_id) REFERENCES platform_proof_of_repair_records(id),
    FOREIGN KEY (dispatch_id) REFERENCES platform_fulfilment_dispatches(id)
);

CREATE TABLE IF NOT EXISTS platform_warranty_policies (
    id TEXT PRIMARY KEY,
    policy_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    coverage_days INTEGER NOT NULL DEFAULT 30,
    coverage_scope TEXT NOT NULL,
    exclusions_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_warranty_cases (
    id TEXT PRIMARY KEY,
    warranty_code TEXT NOT NULL UNIQUE,
    acceptance_record_id TEXT,
    proof_of_repair_id TEXT,
    dispatch_id TEXT,
    policy_id TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    severity TEXT NOT NULL DEFAULT 'medium',
    claim_type TEXT NOT NULL DEFAULT 'fit_or_function_issue',
    claim_summary TEXT NOT NULL,
    resolution_summary TEXT,
    evidence_json TEXT NOT NULL DEFAULT '{}',
    opened_by TEXT,
    assigned_to TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    resolved_at TEXT,
    FOREIGN KEY (acceptance_record_id) REFERENCES platform_customer_acceptance_records(id),
    FOREIGN KEY (policy_id) REFERENCES platform_warranty_policies(id)
);

CREATE TABLE IF NOT EXISTS platform_post_repair_support_tickets (
    id TEXT PRIMARY KEY,
    ticket_code TEXT NOT NULL UNIQUE,
    acceptance_record_id TEXT,
    warranty_case_id TEXT,
    dispatch_id TEXT,
    customer_email TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'medium',
    category TEXT NOT NULL DEFAULT 'post_repair_question',
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    response_summary TEXT,
    created_by TEXT,
    assigned_to TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    resolved_at TEXT,
    FOREIGN KEY (acceptance_record_id) REFERENCES platform_customer_acceptance_records(id),
    FOREIGN KEY (warranty_case_id) REFERENCES platform_warranty_cases(id)
);

CREATE TABLE IF NOT EXISTS platform_customer_feedback_records (
    id TEXT PRIMARY KEY,
    acceptance_record_id TEXT,
    dispatch_id TEXT,
    customer_email TEXT,
    channel TEXT NOT NULL DEFAULT 'pilot_console',
    rating INTEGER NOT NULL DEFAULT 0,
    nps_score INTEGER,
    sentiment TEXT NOT NULL DEFAULT 'neutral',
    feedback_text TEXT NOT NULL,
    follow_up_required INTEGER NOT NULL DEFAULT 0,
    created_by TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (acceptance_record_id) REFERENCES platform_customer_acceptance_records(id)
);

CREATE TABLE IF NOT EXISTS platform_post_repair_review_items (
    id TEXT PRIMARY KEY,
    related_entity_type TEXT NOT NULL,
    related_entity_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'medium',
    review_reason TEXT NOT NULL,
    decision TEXT,
    notes TEXT,
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT
);

CREATE TABLE IF NOT EXISTS platform_post_repair_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_customer_acceptance_policies (id, policy_key, name, status, priority, description, rules_json, created_at, updated_at)
VALUES
('customer-acceptance-policy-proof-required', 'proof_review_before_acceptance', 'Proof review before customer acceptance', 'active', 10, 'Customer acceptance should be requested only after proof-of-repair evidence is available or after an explicit pilot override.', '{"require_proof":true,"allow_pilot_override":true}', datetime('now'), datetime('now')),
('customer-acceptance-policy-window', 'acceptance_window', 'Customer acceptance window', 'active', 20, 'Pilot customers should have a bounded review window before the repair is considered accepted by operator follow-up.', '{"default_days":7,"send_reminder_after_days":3}', datetime('now'), datetime('now')),
('customer-acceptance-policy-issue-escalation', 'issue_escalation', 'Issues create support or warranty review', 'active', 30, 'Rejected or low-satisfaction acceptance decisions must generate support or warranty governance evidence.', '{"create_support_ticket":true,"create_warranty_case_for_severe_issue":true,"low_satisfaction_threshold":3}', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_warranty_policies (id, policy_key, name, status, coverage_days, coverage_scope, exclusions_json, created_at, updated_at)
VALUES
('warranty-policy-pilot-repair', 'pilot_repair_warranty', 'Pilot repair warranty placeholder', 'active', 30, 'Local pilot warranty governance placeholder covering fit/function issues reported after acceptance review.', '["misuse","unverified third-party modifications","normal wear","legal warranty terms not yet approved"]', datetime('now'), datetime('now')),
('warranty-policy-reprint-credit', 'reprint_credit_review', 'Reprint credit review placeholder', 'active', 14, 'Operator review for repair-credit compensation or reprint when a maker/provider repair output is not acceptable.', '["cash refund automation","tax/fiscal handling","external payment refund"]', datetime('now'), datetime('now'));
