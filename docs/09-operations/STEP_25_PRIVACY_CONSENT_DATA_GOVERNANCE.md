# Step 25 — Privacy, Consent & Data Governance v1

## Intent

Step 25 moves Re-born from operational governance toward beta readiness by introducing a local/pilot privacy governance layer.

The platform already has readiness, observability, incidents, notifications and SLA governance. A credible pilot also needs explicit handling for:

- privacy notices;
- consent capture and withdrawal;
- data processing inventory;
- retention rules;
- retention dry-runs;
- data subject requests;
- local subject-access exports.

## Scope

Implemented in this step:

- migration `019_privacy_consent_data_governance.sql`;
- `PrivacyGovernanceService`;
- admin-only API endpoints under `/api/v1/platform/*`;
- readiness check `privacy_governance`;
- prototype console `#/privacy-governance`;
- smoke test `scripts/smoke-privacy-data-governance.ps1`.

## New tables

- `platform_privacy_notices`
- `platform_consent_records`
- `platform_data_processing_records`
- `platform_retention_rules`
- `platform_retention_evaluations`
- `platform_data_subject_requests`
- `platform_data_exports`

## API endpoints

```text
GET  /api/v1/platform/privacy-governance
GET  /api/v1/platform/privacy-notices
GET  /api/v1/platform/consent-records
POST /api/v1/platform/consent-records
POST /api/v1/platform/consent-records/{id}/withdraw
GET  /api/v1/platform/data-processing-records
GET  /api/v1/platform/retention-rules
POST /api/v1/platform/retention/evaluate
GET  /api/v1/platform/retention-evaluations
GET  /api/v1/platform/data-subject-requests
POST /api/v1/platform/data-subject-requests
POST /api/v1/platform/data-subject-requests/{id}/resolve
POST /api/v1/platform/data-subject-requests/{id}/export
GET  /api/v1/platform/data-exports
```

## Design decisions

### Retention is dry-run only

Step 25 does not delete repair data. Retention evaluation counts candidate records and records evidence for human review.

This avoids unsafe deletion while still making retention governance demonstrable.

### Exports are local JSON payloads

Subject-access exports are stored in SQLite as JSON payloads. They include user metadata, repair cases, attachment metadata, consent records and data subject requests.

Uploaded binary files are not embedded.

### Legal approval is still out of scope

This step provides a governance mechanism, not final legal/GDPR approval. Before real production, the project still needs:

- final privacy policy;
- terms of service;
- real consent UX;
- AI provider data-processing review;
- payment provider data-processing review;
- email/SMS/webhook transport review;
- retention deletion and backup retention policy review.

## Smoke test

With the PHP server running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-privacy-data-governance.ps1
```

The smoke test validates:

- `/api/health` capabilities;
- `/api/ready` privacy governance check;
- admin login;
- privacy dashboard;
- privacy notices;
- consent record creation and withdrawal;
- processing records;
- retention rules and dry-run evaluation;
- data subject request creation;
- subject export generation;
- data subject request resolution.

## Product principle

This step exists to protect the Repair Journey.

Users do not upload repair photos, object descriptions and provider interactions because they want a data platform. They do it because they want an object to work again. Privacy governance must therefore be embedded in the operating system of Re-born, not bolted on later.
