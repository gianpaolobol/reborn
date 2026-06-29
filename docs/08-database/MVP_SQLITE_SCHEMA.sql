-- Re-born MVP SQLite Schema v0.1
-- Development schema. Production target: MariaDB/MySQL.

PRAGMA foreign_keys = ON;

CREATE TABLE users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    primary_role TEXT NOT NULL DEFAULT 'repair_user',
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE user_roles (
    user_id TEXT NOT NULL,
    role TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (user_id, role),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE repair_cases (
    id TEXT PRIMARY KEY,
    public_ref TEXT NOT NULL UNIQUE,
    user_id TEXT NOT NULL,
    object_name TEXT,
    description TEXT,
    brand TEXT,
    model TEXT,
    dimensions_note TEXT,
    material_clues TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    selected_path TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    submitted_at TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE repair_photos (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    file_path TEXT NOT NULL,
    original_name TEXT,
    mime_type TEXT NOT NULL,
    size_bytes INTEGER NOT NULL,
    photo_role TEXT DEFAULT 'unspecified',
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE
);

CREATE TABLE product_categories (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    parent_id TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (parent_id) REFERENCES product_categories(id)
);

CREATE TABLE product_types (
    id TEXT PRIMARY KEY,
    category_id TEXT NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
);

CREATE TABLE components (
    id TEXT PRIMARY KEY,
    product_type_id TEXT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (product_type_id) REFERENCES product_types(id)
);

CREATE TABLE damage_types (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE repair_dna (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL UNIQUE,
    category_id TEXT,
    product_type_id TEXT,
    component_id TEXT,
    damage_type_id TEXT,
    confidence TEXT NOT NULL DEFAULT 'low',
    safety_level TEXT NOT NULL DEFAULT 'normal',
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES product_categories(id),
    FOREIGN KEY (product_type_id) REFERENCES product_types(id),
    FOREIGN KEY (component_id) REFERENCES components(id),
    FOREIGN KEY (damage_type_id) REFERENCES damage_types(id)
);

CREATE TABLE classification_history (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    admin_user_id TEXT,
    previous_json TEXT,
    new_json TEXT NOT NULL,
    reason TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id),
    FOREIGN KEY (admin_user_id) REFERENCES users(id)
);

CREATE TABLE repair_paths (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    path_type TEXT NOT NULL,
    availability TEXT NOT NULL DEFAULT 'available',
    confidence TEXT NOT NULL DEFAULT 'medium',
    estimated_cost_note TEXT,
    estimated_time_note TEXT,
    explanation TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE
);

CREATE TABLE provider_profiles (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    location_note TEXT,
    service_area_note TEXT,
    status TEXT NOT NULL DEFAULT 'pending_review',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE provider_capabilities (
    id TEXT PRIMARY KEY,
    provider_id TEXT NOT NULL,
    technology TEXT NOT NULL,
    material TEXT,
    machine_note TEXT,
    max_size_note TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES provider_profiles(id) ON DELETE CASCADE
);

CREATE TABLE quote_requests (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'sent',
    message TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id),
    FOREIGN KEY (provider_id) REFERENCES provider_profiles(id)
);

CREATE TABLE quotes (
    id TEXT PRIMARY KEY,
    quote_request_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    price_cents INTEGER,
    currency TEXT DEFAULT 'EUR',
    turnaround_note TEXT,
    material_note TEXT,
    provider_notes TEXT,
    status TEXT NOT NULL DEFAULT 'quoted',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (quote_request_id) REFERENCES quote_requests(id),
    FOREIGN KEY (provider_id) REFERENCES provider_profiles(id)
);

CREATE TABLE maker_profiles (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    skills_note TEXT,
    software_note TEXT,
    portfolio_url TEXT,
    status TEXT NOT NULL DEFAULT 'pending_review',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE models (
    id TEXT PRIMARY KEY,
    maker_id TEXT,
    title TEXT NOT NULL,
    description TEXT,
    category_id TEXT,
    component_id TEXT,
    compatibility_note TEXT,
    file_placeholder TEXT,
    license_note TEXT,
    status TEXT NOT NULL DEFAULT 'pending_review',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (maker_id) REFERENCES maker_profiles(id),
    FOREIGN KEY (category_id) REFERENCES product_categories(id),
    FOREIGN KEY (component_id) REFERENCES components(id)
);

CREATE TABLE bounties (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    created_by_user_id TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    reward_note TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    expires_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE bounty_submissions (
    id TEXT PRIMARY KEY,
    bounty_id TEXT NOT NULL,
    maker_id TEXT NOT NULL,
    model_id TEXT,
    message TEXT,
    status TEXT NOT NULL DEFAULT 'submitted',
    created_at TEXT NOT NULL,
    FOREIGN KEY (bounty_id) REFERENCES bounties(id),
    FOREIGN KEY (maker_id) REFERENCES maker_profiles(id),
    FOREIGN KEY (model_id) REFERENCES models(id)
);

CREATE TABLE repair_outcomes (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL UNIQUE,
    outcome TEXT NOT NULL,
    notes TEXT,
    final_photo_path TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id)
);

CREATE TABLE safety_flags (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT NOT NULL,
    flag_type TEXT NOT NULL,
    severity TEXT NOT NULL DEFAULT 'medium',
    notes TEXT,
    created_by_user_id TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE knowledge_signals (
    id TEXT PRIMARY KEY,
    repair_case_id TEXT,
    signal_type TEXT NOT NULL,
    subject_type TEXT NOT NULL,
    subject_id TEXT,
    payload_json TEXT NOT NULL,
    confidence TEXT DEFAULT 'medium',
    created_at TEXT NOT NULL,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id)
);

CREATE TABLE analytics_events (
    id TEXT PRIMARY KEY,
    user_id TEXT,
    event_name TEXT NOT NULL,
    entity_type TEXT,
    entity_id TEXT,
    payload_json TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX idx_repair_cases_user ON repair_cases(user_id);
CREATE INDEX idx_repair_cases_status ON repair_cases(status);
CREATE INDEX idx_repair_dna_case ON repair_dna(repair_case_id);
CREATE INDEX idx_quote_requests_provider ON quote_requests(provider_id);
CREATE INDEX idx_models_status ON models(status);
CREATE INDEX idx_knowledge_signals_type ON knowledge_signals(signal_type);
CREATE INDEX idx_analytics_events_name ON analytics_events(event_name);
