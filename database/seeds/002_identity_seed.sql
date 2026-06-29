INSERT OR IGNORE INTO users (id, email, name, role, password_hash, status, email_verified_at, created_at, updated_at) VALUES
('user-demo-repair', 'repair.user@reborn.local', 'Demo Repair User', 'repair_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.O/Lu7/93LLzWz8K2O', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-maker', 'maker@reborn.local', 'Demo Maker', 'maker', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.O/Lu7/93LLzWz8K2O', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-provider', 'provider@reborn.local', 'Demo Provider', 'provider', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.O/Lu7/93LLzWz8K2O', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-enterprise', 'enterprise@reborn.local', 'Demo Enterprise', 'enterprise', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.O/Lu7/93LLzWz8K2O', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-admin', 'admin@reborn.local', 'Demo Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.O/Lu7/93LLzWz8K2O', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z');

UPDATE repair_cases SET owner_id = 'user-demo-repair' WHERE owner_id IS NULL;
