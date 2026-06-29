# STEP 32 — CAD/Geometry Validation & Printability Governance v1

## Goal

Step 32 adds a pilot governance layer between AI/user/maker geometry and the rest of the Repair Journey.

Re-born must not treat a generated STL, uploaded STEP file or maker model as automatically printable or safe. The platform now records geometry assets, evaluates them against pilot printability profiles, creates findings, and opens human review items before provider routing or maker publication.

## What this step adds

- Geometry asset registry for uploaded, maker-submitted or AI-generated artifact stubs.
- Validation profiles for FDM PLA/PETG and TPU pilot repair parts.
- Printability rules for supported file format, machine bounding box, thin-wall risk and human review.
- Validation run records with score, decision and checks JSON.
- Printability findings.
- Human geometry review queue.
- Geometry governance audit log.
- Prototype admin console at `#/geometry-printability`.
- Readiness check `geometry_printability`.
- Smoke test `scripts/smoke-geometry-printability-governance.ps1`.

## Out of scope

Step 32 does **not** run a real CAD kernel, slicer, mesh repair engine or certified engineering analysis.

Still deferred:

- Real STL/OBJ/STEP parsing.
- Mesh manifold checks.
- Automatic mesh repair.
- Slicer simulation.
- Structural/FEA validation.
- Real provider machine profile integration.
- Public model publication.

## Main API endpoints

```text
GET  /api/v1/platform/geometry-printability
GET  /api/v1/platform/geometry-validation-profiles
GET  /api/v1/platform/printability-rules
GET  /api/v1/platform/geometry-assets
POST /api/v1/platform/geometry-assets
POST /api/v1/platform/geometry-assets/{id}/evaluate
GET  /api/v1/platform/geometry-validation-runs
GET  /api/v1/platform/printability-findings
GET  /api/v1/platform/geometry-review-items
POST /api/v1/platform/geometry-review-items/{id}/review
GET  /api/v1/platform/geometry-governance-audit-log
```

## Verification

Run the server:

```powershell
php -S 127.0.0.1:8080 -t public public/index.php
```

Then from a second PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-geometry-printability-governance.ps1
```

Recommended regression checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-provider-sandbox-orchestration.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-geometry-printability-governance.ps1
```

## Product principle

The user is not asking for an STL. The user wants an object to work again.

Geometry validation exists to protect the Repair Journey from weak, unsafe or unreviewed parts.
