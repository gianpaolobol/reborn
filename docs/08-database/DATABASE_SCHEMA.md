# Database Schema — Conceptual v0.1

## Core tables

### users

- id
- email
- password_hash
- display_name
- role
- status
- created_at
- updated_at

### repair_cases

- id
- user_id
- title
- description
- status
- category_id
- product_id
- component_id
- confidence_score
- selected_repair_path_id
- location_id
- created_at
- updated_at

### repair_assets

- id
- repair_case_id
- file_path
- file_type
- asset_type
- metadata_json
- created_at

### repair_paths

- id
- repair_case_id
- path_type
- title
- description
- confidence_score
- status
- estimated_cost
- estimated_time
- created_at

### repair_outcomes

- id
- repair_case_id
- success_status
- fit_rating
- durability_rating
- notes
- created_at

### cad_models

- id
- maker_id
- title
- description
- category_id
- component_id
- price_type
- price_amount
- license_type
- verification_status
- trust_score
- created_at
- updated_at

### cad_model_versions

- id
- cad_model_id
- version_label
- file_path
- changelog
- status
- created_at

### providers

- id
- user_id
- display_name
- provider_type
- location_id
- status
- trust_score
- created_at

### provider_capabilities

- id
- provider_id
- method
- material
- max_dimensions_json
- notes

### provider_requests

- id
- repair_case_id
- provider_id
- status
- user_notes
- provider_quote
- created_at
- updated_at

### wallets

- id
- owner_type
- owner_id
- balance_credits
- created_at

### wallet_transactions

- id
- wallet_id
- transaction_type
- amount
- reference_type
- reference_id
- status
- created_at

### trust_signals

- id
- target_type
- target_id
- signal_type
- value
- evidence_type
- evidence_id
- created_at

### knowledge_edges

- id
- source_type
- source_id
- relation_type
- target_type
- target_id
- confidence
- evidence_type
- evidence_id
- created_at
