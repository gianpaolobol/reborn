CREATE INDEX IF NOT EXISTS idx_repair_cases_owner_status ON repair_cases(owner_id, status);
CREATE INDEX IF NOT EXISTS idx_repair_cases_updated_at ON repair_cases(updated_at);
CREATE INDEX IF NOT EXISTS idx_cad_models_maker ON cad_models(maker_id);

UPDATE repair_cases SET owner_id = 'user-demo-repair' WHERE owner_id IS NULL;
UPDATE cad_models SET maker_id = 'user-demo-maker' WHERE id IN ('cad-demo-garmin-strap-connector', 'cad-demo-washing-machine-handle');

INSERT OR IGNORE INTO repair_cases (id, owner_id, title, description, category, status, recognized_product, recognized_component, confidence_score, created_at, updated_at) VALUES
('case-demo-002', 'user-demo-repair', 'Oakley Eye Jacket missing nose bridge', 'The eyewear frame is original but the nose bridge component is missing after storage.', 'eyewear', 'intake_received', NULL, NULL, 0, '2026-06-29T00:10:00Z', '2026-06-29T00:10:00Z'),
('case-demo-003', 'user-demo-enterprise', 'Enterprise batch: appliance knob replacement', 'A facility management team needs a repeatable replacement path for broken appliance knobs.', 'home_appliance', 'diagnosed', 'Generic appliance control panel', 'control knob', 0.72, '2026-06-29T00:20:00Z', '2026-06-29T00:20:00Z');
