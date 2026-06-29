INSERT OR IGNORE INTO users (id, email, name, role, password_hash, status, email_verified_at, created_at, updated_at) VALUES
('user-demo-repair', 'repair.user@reborn.local', 'Demo Repair User', 'repair_user', '$2y$10$w8FgkVBkAPnu5YIP/ss8Oe7WOPT5clQnm.kNvLCKWDAWEXbwxm1X.', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-maker', 'maker@reborn.local', 'Demo Maker', 'maker', '$2y$10$w8FgkVBkAPnu5YIP/ss8Oe7WOPT5clQnm.kNvLCKWDAWEXbwxm1X.', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-provider', 'provider@reborn.local', 'Demo Provider', 'provider', '$2y$10$w8FgkVBkAPnu5YIP/ss8Oe7WOPT5clQnm.kNvLCKWDAWEXbwxm1X.', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-enterprise', 'enterprise@reborn.local', 'Demo Enterprise', 'enterprise', '$2y$10$w8FgkVBkAPnu5YIP/ss8Oe7WOPT5clQnm.kNvLCKWDAWEXbwxm1X.', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z'),
('user-demo-admin', 'admin@reborn.local', 'Demo Admin', 'admin', '$2y$10$w8FgkVBkAPnu5YIP/ss8Oe7WOPT5clQnm.kNvLCKWDAWEXbwxm1X.', 'active', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z');

UPDATE repair_cases SET owner_id = 'user-demo-repair' WHERE owner_id IS NULL;

-- Keep demo credentials deterministic and repair older local databases where the demo rows
-- already existed before this seed was corrected. The password for every demo account is: password
UPDATE users
SET password_hash = '$2y$10$w8FgkVBkAPnu5YIP/ss8Oe7WOPT5clQnm.kNvLCKWDAWEXbwxm1X.',
    status = 'active',
    updated_at = '2026-06-30T00:00:00Z'
WHERE email IN (
    'repair.user@reborn.local',
    'maker@reborn.local',
    'provider@reborn.local',
    'enterprise@reborn.local',
    'admin@reborn.local'
);
