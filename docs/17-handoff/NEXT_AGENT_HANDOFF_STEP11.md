# NEXT AGENT HANDOFF — STEP 11

## Objective

Step 11 implemented the Repair Intake File Upload & AI Recognition Pipeline MVP.

The platform now links user-uploaded repair evidence to a repair case and creates a synchronous mock AI recognition job that produces a structured preliminary diagnosis.

## Current status

Implemented:

- Attachment validation hardened.
- Upload storage path: `storage/uploads/repair-cases/{case_id}/`.
- New `recognition_jobs` migration.
- New AI domain/job repository/application/controller slice.
- New recognition endpoints.
- New domain events:
  - `ai.recognition_requested`
  - `ai.recognition_completed`
- Existing attachment event verified:
  - `repair.attachment_added`
- Prototype `#/capture` screen upgraded to upload evidence and run recognition.
- API client methods added.
- Step 11 smoke test added.
- Step 11 documentation added.

## Key endpoints

- `POST /api/v1/repair-cases/{id}/attachments`
- `GET /api/v1/repair-cases/{id}/attachments`
- `POST /api/v1/repair-cases/{id}/recognition-jobs`
- `GET /api/v1/repair-cases/{id}/recognition-jobs`
- `GET /api/v1/recognition-jobs/{id}`

## Test commands

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Second PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
```

## Commit recommendation

If all tests pass:

```powershell
git status
git add .
git commit -m "repair: add upload and AI recognition pipeline MVP"
git push
```

Do not commit:

- `storage/database/reborn.sqlite`
- `storage/logs/*`
- `storage/uploads/*`
- debug files
- temporary upload files

## Decisions made

- Recognition is synchronous in the MVP to avoid queue complexity.
- The job model is still explicit so it can later move to async workers.
- Attachments belong to repair cases, never to a generic file marketplace.
- UI uses the existing `#/capture` repair journey step to avoid adding a detached upload page.
- Mock fallback remains available for static prototype review.

## Open questions

- When to introduce malware scanning and signed upload/download URLs.
- Whether production storage will be S3-compatible object storage.
- Whether Step 12 should update `repair_paths` directly from the recognition result.
- How much maker/provider data should influence the next recommendation.

## Suggested Step 12

`Repair Path Decision Engine v1`.

Turn the recognition result into ranked next actions:

1. Identify existing part.
2. Generate repair part draft.
3. Find provider.
4. Ask for more photos or dimensions.
5. Open maker bounty.
6. Escalate to enterprise workflow.
