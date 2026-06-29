CREATE TABLE IF NOT EXISTS provider_governance_actions (
    id TEXT PRIMARY KEY,
    provider_id TEXT NOT NULL,
    action_type TEXT NOT NULL,
    severity TEXT NOT NULL,
    status TEXT NOT NULL,
    reason TEXT NOT NULL,
    notes TEXT NULL,
    score_adjustment REAL NOT NULL DEFAULT 0,
    expires_at TEXT NULL,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    resolved_at TEXT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_provider_governance_actions_provider ON provider_governance_actions(provider_id);
CREATE INDEX IF NOT EXISTS idx_provider_governance_actions_status ON provider_governance_actions(status);
CREATE INDEX IF NOT EXISTS idx_provider_governance_actions_type ON provider_governance_actions(action_type);
CREATE INDEX IF NOT EXISTS idx_provider_governance_actions_created_at ON provider_governance_actions(created_at);

CREATE TABLE IF NOT EXISTS provider_ranking_snapshots (
    id TEXT PRIMARY KEY,
    status TEXT NOT NULL,
    ranking_formula_version TEXT NOT NULL,
    provider_count INTEGER NOT NULL DEFAULT 0,
    ranking_json TEXT NOT NULL DEFAULT '[]',
    policy_json TEXT NOT NULL DEFAULT '{}',
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_provider_ranking_snapshots_created_at ON provider_ranking_snapshots(created_at);
CREATE INDEX IF NOT EXISTS idx_provider_ranking_snapshots_status ON provider_ranking_snapshots(status);

CREATE TABLE IF NOT EXISTS marketplace_governance_audit (
    id TEXT PRIMARY KEY,
    actor_id TEXT NOT NULL,
    action TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT NOT NULL,
    payload_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_marketplace_governance_audit_actor ON marketplace_governance_audit(actor_id);
CREATE INDEX IF NOT EXISTS idx_marketplace_governance_audit_subject ON marketplace_governance_audit(subject_type, subject_id);
CREATE INDEX IF NOT EXISTS idx_marketplace_governance_audit_created_at ON marketplace_governance_audit(created_at);
