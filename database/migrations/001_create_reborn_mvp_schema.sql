CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'repair_user',
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS repair_cases (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    category TEXT NOT NULL,
    status TEXT NOT NULL,
    recognized_product TEXT NULL,
    recognized_component TEXT NULL,
    confidence_score REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_repair_cases_status ON repair_cases(status);
CREATE INDEX IF NOT EXISTS idx_repair_cases_category ON repair_cases(category);

CREATE TABLE IF NOT EXISTS repair_paths (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    confidence_score REAL NOT NULL DEFAULT 0,
    estimated_price_cents INTEGER NOT NULL DEFAULT 0,
    estimated_days INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_repair_paths_case ON repair_paths(repair_case_id);

CREATE TABLE IF NOT EXISTS providers (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    city TEXT NOT NULL,
    country TEXT NOT NULL,
    capabilities TEXT NOT NULL DEFAULT '[]',
    rating REAL NOT NULL DEFAULT 0,
    average_lead_time_days INTEGER NOT NULL DEFAULT 5,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cad_models (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    component_label TEXT NOT NULL,
    maker_id TEXT NULL,
    license TEXT NOT NULL DEFAULT 'platform_verified',
    royalty_percent REAL NOT NULL DEFAULT 0,
    verification_status TEXT NOT NULL DEFAULT 'draft',
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS knowledge_nodes (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL,
    label TEXT NOT NULL,
    confidence_score REAL NOT NULL DEFAULT 0,
    metadata TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS knowledge_edges (
    id TEXT PRIMARY KEY,
    source_node_id TEXT NOT NULL,
    target_node_id TEXT NOT NULL,
    relation TEXT NOT NULL,
    confidence_score REAL NOT NULL DEFAULT 0,
    metadata TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    FOREIGN KEY (source_node_id) REFERENCES knowledge_nodes(id),
    FOREIGN KEY (target_node_id) REFERENCES knowledge_nodes(id)
);

CREATE TABLE IF NOT EXISTS domain_events (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    payload TEXT NOT NULL DEFAULT '{}',
    occurred_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_domain_events_name ON domain_events(name);
CREATE INDEX IF NOT EXISTS idx_domain_events_occurred_at ON domain_events(occurred_at);
