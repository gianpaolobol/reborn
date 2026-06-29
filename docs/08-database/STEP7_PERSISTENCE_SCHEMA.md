# Step 7 Persistence Schema

## `repair_attachments`

Stores references to uploaded repair evidence and technical assets.

Fields:

- `id`
- `repair_case_id`
- `original_filename`
- `stored_path`
- `mime_type`
- `size_bytes`
- `sha256`
- `kind`
- `created_at`

Strategic purpose:

- feeds Recognition Engine,
- improves Knowledge Graph evidence,
- enables repair-case traceability,
- prepares CAD and AI generation workflows.

## `audit_log`

Baseline table for future trust, moderation and enterprise traceability.

Not fully wired yet. It will become part of the future Trust Engine.
