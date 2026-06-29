-- Re-born MVP seed data v0.1

INSERT INTO product_categories (id, name, slug, parent_id, created_at) VALUES
('cat_household', 'Household objects', 'household-objects', NULL, datetime('now')),
('cat_small_appliances', 'Small appliances', 'small-appliances', NULL, datetime('now')),
('cat_furniture', 'Furniture', 'furniture', NULL, datetime('now'));

INSERT INTO product_types (id, category_id, name, slug, created_at) VALUES
('ptype_coffee_machine', 'cat_small_appliances', 'Coffee machine', 'coffee-machine', datetime('now')),
('ptype_vacuum_cleaner', 'cat_small_appliances', 'Vacuum cleaner', 'vacuum-cleaner', datetime('now')),
('ptype_chair', 'cat_furniture', 'Chair', 'chair', datetime('now')),
('ptype_generic_plastic_object', 'cat_household', 'Generic plastic object', 'generic-plastic-object', datetime('now'));

INSERT INTO components (id, product_type_id, name, slug, description, created_at) VALUES
('comp_knob', NULL, 'Knob', 'knob', 'Rotating control component.', datetime('now')),
('comp_clip', NULL, 'Clip', 'clip', 'Small fastening component.', datetime('now')),
('comp_cover', NULL, 'Cover', 'cover', 'External or protective cover.', datetime('now')),
('comp_handle', NULL, 'Handle', 'handle', 'Gripping component.', datetime('now')),
('comp_foot', NULL, 'Foot', 'foot', 'Support foot or pad.', datetime('now')),
('comp_bracket', NULL, 'Bracket', 'bracket', 'Support or mounting component.', datetime('now')),
('comp_hinge', NULL, 'Hinge', 'hinge', 'Rotational joint component.', datetime('now'));

INSERT INTO damage_types (id, name, slug, description, created_at) VALUES
('damage_cracked', 'Cracked', 'cracked', 'Visible crack or partial fracture.', datetime('now')),
('damage_broken_connector', 'Broken connector', 'broken-connector', 'Internal or external connector broke.', datetime('now')),
('damage_missing_part', 'Missing part', 'missing-part', 'Part is missing and must be reconstructed or sourced.', datetime('now')),
('damage_worn', 'Worn', 'worn', 'Part is worn and no longer performs as expected.', datetime('now')),
('damage_unknown', 'Unknown', 'unknown', 'Damage type needs review.', datetime('now'));
