# STEP 11 — Repair Upload & AI Recognition Pipeline MVP

## Goal

Step 11 connects the Repair Journey to real evidence: users can attach photos, manuals or CAD files to a repair case and request a preliminary AI recognition job.

This is not a generic STL marketplace flow. The upload belongs to a repair case and exists to help the object return to function.

## Implemented scope

- Hardened repair attachment validation for MIME type and 15 MB maximum file size.
- Runtime storage path for uploads moved to `storage/uploads/repair-cases/{repair_case_id}/`.
- New `recognition_jobs` SQLite table.
- New AI bounded slice for recognition job persistence.
- New AI domain events:
  - `ai.recognition_requested`
  - `ai.recognition_completed`
- Existing repair event retained and validated:
  - `repair.attachment_added`
- New API endpoints for recognition jobs.
- Prototype upload screen connected to the API client.
- Mock fallback for static/offline prototype mode.
- Smoke test for end-to-end upload and recognition.

## API endpoints

### Attachments

Existing endpoints were kept and strengthened:

- `POST /api/v1/repair-cases/{id}/attachments`
- `GET /api/v1/repair-cases/{id}/attachments`

Upload requests require a Bearer token and are constrained by the repair case access policy.

Allowed MVP file types:

- `image/jpeg`
- `image/png`
- `image/webp`
- `application/pdf`
- `model/stl`
- `application/octet-stream` only for `.stl`, `.step`, `.stp`, `.obj`

Maximum file size: 15 MB.

### AI recognition jobs

#### `POST /api/v1/repair-cases/{id}/recognition-jobs`

Request body:

```json
{
  "attachment_ids": ["..."]
}
```

MVP behavior:

1. Authenticates the user.
2. Checks repair case mutation rights.
3. Verifies all attachments belong to the repair case.
4. Creates a `recognition_jobs` row with status `requested`.
5. Emits `ai.recognition_requested`.
6. Runs synchronous mock AI recognition.
7. Completes the job.
8. Emits `ai.recognition_completed`.
9. Returns the full job payload.

#### `GET /api/v1/repair-cases/{id}/recognition-jobs`

Returns jobs linked to the selected repair case.

#### `GET /api/v1/recognition-jobs/{id}`

Returns a single recognition job if the authenticated user can view the linked repair case.

## Data model

`recognition_jobs` fields:

- `id`
- `repair_case_id`
- `requested_by`
- `status`
- `input_attachment_ids`
- `result_json`
- `error_message`
- `created_at`
- `started_at`
- `completed_at`

Statuses:

- `requested`
- `processing`
- `completed`
- `failed`

## Mock AI result

The MVP result contains:

- `object_guess`
- `damage_assessment`
- `recommended_next_step`
- `suggested_inputs`
- `repair_notes`

The result is intentionally preliminary and must not be treated as final manufacturability validation.

## Prototype behavior

The `#/capture` screen now represents Step 11:

- Upload photos for repair diagnosis.
- Require login in Live API mode.
- Require an active repair case.
- Select local files.
- Upload selected files to the repair case.
- List uploaded attachments.
- Run AI recognition.
- Display a diagnosis timeline.
- Display the structured mock AI result.

The screen still supports mock fallback when the backend API is not live.

## How to test

Start from a clean Step 10 database and run:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

In another PowerShell window:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
```

Expected Step 11 output:

```text
Repair upload and AI recognition smoke test passed.
```

## MVP limits

- Recognition is synchronous and mocked.
- No queue worker yet.
- No real computer vision yet.
- No malware scanning yet.
- No signed download URL yet.
- No production object storage yet.
- No human QA workflow yet.

## Step 12 direction

The natural Step 12 is `Repair Path Decision Engine v1`: turn AI recognition output into ranked repair actions such as identify existing part, generate part, find provider, ask more photos or open a maker bounty.
