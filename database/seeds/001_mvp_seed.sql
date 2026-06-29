INSERT OR IGNORE INTO providers (id, name, city, country, capabilities, rating, average_lead_time_days, created_at) VALUES
('provider-bologna-lab', 'Bologna Repair Lab', 'Bologna', 'IT', '["FDM", "PETG", "TPU", "CAD validation"]', 4.8, 3, '2026-06-29T00:00:00Z'),
('provider-milan-maker', 'Milano Distributed Manufacturing', 'Milano', 'IT', '["FDM", "SLA", "ASA", "small batch"]', 4.7, 4, '2026-06-29T00:00:00Z'),
('provider-barcelona-circular', 'Barcelona Circular Fab', 'Barcelona', 'ES', '["FDM", "SLS partner", "repair validation"]', 4.6, 5, '2026-06-29T00:00:00Z');

INSERT OR IGNORE INTO knowledge_nodes (id, type, label, confidence_score, metadata, created_at) VALUES
('node-oakley-eye-jacket', 'product', 'Oakley Eye Jacket', 0.84, '{"product":"Oakley Eye Jacket","component":"temple hinge / nose bridge","repairability":"medium"}', '2026-06-29T00:00:00Z'),
('node-garmin-strap', 'component', 'Garmin wearable strap connector', 0.79, '{"product":"Garmin wearable","component":"strap connector","repairability":"high"}', '2026-06-29T00:00:00Z'),
('node-washing-machine-handle', 'component', 'Washing machine handle', 0.74, '{"product":"Washing machine","component":"handle","repairability":"high"}', '2026-06-29T00:00:00Z');

INSERT OR IGNORE INTO cad_models (id, title, component_label, maker_id, license, royalty_percent, verification_status, created_at) VALUES
('cad-demo-garmin-strap-connector', 'Garmin strap connector repair model', 'strap connector', 'maker-demo-001', 'royalty_marketplace', 12.5, 'verified', '2026-06-29T00:00:00Z'),
('cad-demo-washing-machine-handle', 'Universal washing machine handle prototype', 'washing machine handle', 'maker-demo-002', 'royalty_marketplace', 10, 'human_review_required', '2026-06-29T00:00:00Z');

INSERT OR IGNORE INTO repair_cases (id, title, description, category, status, recognized_product, recognized_component, confidence_score, created_at, updated_at) VALUES
('case-demo-001', 'Broken Garmin strap connector', 'The strap connector on a wearable device is cracked and the watch cannot be used during running.', 'wearable', 'diagnosed', 'Garmin wearable', 'strap / charging port cover', 0.78, '2026-06-29T00:00:00Z', '2026-06-29T00:00:00Z');
