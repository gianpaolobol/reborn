# Step 7 — Backend Persistence Hardening

This step converts the MVP backend skeleton into a more reliable execution base.

## Added

- Uniform API error model with request IDs.
- Malformed JSON detection.
- Method-not-allowed responses.
- Centralized exception handling and daily API logs.
- Stronger repair-case validation.
- Attachment persistence for images, CAD files and repair evidence.
- Local file storage under `storage/app/uploads`.
- `repair_attachments` table.
- `audit_log` table baseline.
- Domain event endpoint for development inspection.
- Feature smoke test script.

## New endpoints

```http
GET  /api/v1/repair-cases/{id}/attachments
POST /api/v1/repair-cases/{id}/attachments
GET  /api/v1/domain-events?limit=50
```

## Attachment upload contract

Use `multipart/form-data`:

- `file`: required.
- `kind`: optional, defaults to `repair_asset`.

Allowed extensions:

- `jpg`, `jpeg`, `png`, `webp`
- `stl`, `obj`, `step`, `stp`, `3mf`
- `pdf`

Max size: 10 MB.

## Run locally

```powershell
php scripts/doctor.php
php scripts/setup-dev.php
php scripts/run-feature-tests.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Then, in another PowerShell window:

```powershell
.\scripts\smoke-backend-hardening.ps1
```

## Strategic note

This is still not a full production backend. It is the minimum hardened foundation needed before implementing real authentication, payment, provider offers, CAD model marketplace and AI file analysis.
