CREATE TABLE IF NOT EXISTS repair_orders (
    id TEXT PRIMARY KEY,
    quote_request_id TEXT NOT NULL,
    provider_match_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    provider_id TEXT NOT NULL,
    ordered_by TEXT NOT NULL,
    status TEXT NOT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    subtotal_cents INTEGER NOT NULL DEFAULT 0,
    platform_fee_cents INTEGER NOT NULL DEFAULT 0,
    provider_payout_cents INTEGER NOT NULL DEFAULT 0,
    total_cents INTEGER NOT NULL DEFAULT 0,
    order_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    confirmed_at TEXT NULL,
    cancelled_at TEXT NULL,
    FOREIGN KEY (quote_request_id) REFERENCES provider_quote_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_match_id) REFERENCES provider_matches(id) ON DELETE CASCADE,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (ordered_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_repair_orders_quote ON repair_orders(quote_request_id);
CREATE INDEX IF NOT EXISTS idx_repair_orders_case ON repair_orders(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_repair_orders_provider ON repair_orders(provider_id);
CREATE INDEX IF NOT EXISTS idx_repair_orders_status ON repair_orders(status);
CREATE INDEX IF NOT EXISTS idx_repair_orders_created_at ON repair_orders(created_at);

CREATE TABLE IF NOT EXISTS payment_intents (
    id TEXT PRIMARY KEY,
    repair_order_id TEXT NOT NULL,
    quote_request_id TEXT NOT NULL,
    repair_case_id TEXT NOT NULL,
    requested_by TEXT NOT NULL,
    provider TEXT NOT NULL DEFAULT 'mock',
    status TEXT NOT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    amount_cents INTEGER NOT NULL DEFAULT 0,
    client_secret TEXT NOT NULL,
    payment_url TEXT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    confirmed_at TEXT NULL,
    cancelled_at TEXT NULL,
    FOREIGN KEY (repair_order_id) REFERENCES repair_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_request_id) REFERENCES provider_quote_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (repair_case_id) REFERENCES repair_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_payment_intents_order ON payment_intents(repair_order_id);
CREATE INDEX IF NOT EXISTS idx_payment_intents_case ON payment_intents(repair_case_id);
CREATE INDEX IF NOT EXISTS idx_payment_intents_status ON payment_intents(status);
CREATE INDEX IF NOT EXISTS idx_payment_intents_created_at ON payment_intents(created_at);
