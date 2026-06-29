CREATE TABLE IF NOT EXISTS platform_dispatch_policies (
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

CREATE TABLE IF NOT EXISTS platform_fulfilment_dispatches (
    id TEXT PRIMARY KEY,
    dispatch_code TEXT NOT NULL UNIQUE,
    routing_request_id TEXT,
    routing_match_id TEXT,
    provider_capability_id TEXT,
    machine_profile_id TEXT,
    repair_order_id TEXT,
    fulfilment_id TEXT,
    status TEXT NOT NULL DEFAULT 'planned',
    dispatch_decision TEXT,
    fulfilment_mode TEXT NOT NULL DEFAULT 'shipped',
    carrier TEXT,
    tracking_number TEXT,
    destination_country TEXT NOT NULL DEFAULT 'IT',
    estimated_dispatch_at TEXT,
    estimated_delivery_at TEXT,
    package_requirements_json TEXT NOT NULL DEFAULT '{}',
    operator_notes TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    dispatched_at TEXT,
    delivered_at TEXT,
    closed_at TEXT,
    FOREIGN KEY (routing_request_id) REFERENCES platform_fulfilment_routing_requests(id),
    FOREIGN KEY (routing_match_id) REFERENCES platform_provider_routing_matches(id),
    FOREIGN KEY (provider_capability_id) REFERENCES platform_provider_capability_profiles(id),
    FOREIGN KEY (machine_profile_id) REFERENCES platform_machine_profiles(id)
);

CREATE TABLE IF NOT EXISTS platform_shipment_tracking_events (
    id TEXT PRIMARY KEY,
    dispatch_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'recorded',
    event_type TEXT NOT NULL,
    location TEXT,
    message TEXT NOT NULL,
    evidence_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    occurred_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (dispatch_id) REFERENCES platform_fulfilment_dispatches(id)
);

CREATE TABLE IF NOT EXISTS platform_proof_of_repair_records (
    id TEXT PRIMARY KEY,
    dispatch_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending_review',
    proof_type TEXT NOT NULL DEFAULT 'photo_and_notes',
    summary TEXT NOT NULL,
    evidence_json TEXT NOT NULL DEFAULT '{}',
    quality_score INTEGER NOT NULL DEFAULT 0,
    customer_acceptance_status TEXT NOT NULL DEFAULT 'not_requested',
    customer_notes TEXT,
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT,
    FOREIGN KEY (dispatch_id) REFERENCES platform_fulfilment_dispatches(id)
);

CREATE TABLE IF NOT EXISTS platform_dispatch_review_items (
    id TEXT PRIMARY KEY,
    dispatch_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    priority TEXT NOT NULL DEFAULT 'medium',
    review_reason TEXT NOT NULL,
    decision TEXT,
    notes TEXT,
    created_by TEXT,
    reviewed_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT,
    FOREIGN KEY (dispatch_id) REFERENCES platform_fulfilment_dispatches(id)
);

CREATE TABLE IF NOT EXISTS platform_dispatch_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_dispatch_policies (id, policy_key, name, status, priority, description, rules_json, created_at, updated_at)
VALUES
('dispatch-policy-routing-approved', 'routing_match_required', 'Dispatch requires provider routing evidence', 'active', 10, 'Every dispatch must reference a routing match or routing request selected through provider routing governance.', '{"require_routing_match":true,"allow_manual_override":true}', datetime('now'), datetime('now')),
('dispatch-policy-tracking-evidence', 'tracking_events_required', 'Shipment tracking events must be recorded', 'active', 20, 'A shipment or pickup must leave an auditable event trail before proof-of-repair is accepted.', '{"minimum_events_before_close":2,"allow_local_pickup":true}', datetime('now'), datetime('now')),
('dispatch-policy-proof-of-repair', 'proof_of_repair_required', 'Proof-of-repair required before completion', 'active', 30, 'Provider completion should include structured evidence and an operator review before final acceptance.', '{"required_proof_types":["photo_and_notes","functional_test"],"minimum_quality_score":70}', datetime('now'), datetime('now'));
