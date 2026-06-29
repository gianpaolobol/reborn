# STEP 33 — Provider Capability, Machine Profile & Fulfilment Routing Governance v1

## Goal

Step 33 connects the Step 32 geometry/printability governance layer to provider fulfilment operations.

The product principle remains unchanged: the user is not looking for an STL; the user wants the object to work again. Provider routing therefore scores whether a validated or reviewed repair geometry can be routed to a compatible provider and machine profile.

## Implemented scope

- Provider capability profiles.
- Machine profiles.
- Routing policies.
- Fulfilment routing requests.
- Provider routing matches.
- Match scoring based on process, material, build volume, lead time, budget, quality and geometry release status.
- Human routing review queue.
- Provider routing audit log.
- Admin prototype console at `#/provider-routing`.
- Readiness check `provider_routing`.
- Smoke test `scripts/smoke-provider-routing-governance.ps1`.

## API endpoints

```text
GET  /api/v1/platform/provider-routing
GET  /api/v1/platform/provider-capabilities
GET  /api/v1/platform/machine-profiles
GET  /api/v1/platform/routing-policies
GET  /api/v1/platform/routing-requests
POST /api/v1/platform/routing-requests
POST /api/v1/platform/routing-requests/{id}/evaluate
GET  /api/v1/platform/routing-matches
GET  /api/v1/platform/routing-review-items
POST /api/v1/platform/routing-review-items/{id}/review
GET  /api/v1/platform/provider-routing-audit-log
```

## What remains mock or deferred

Step 33 does **not** implement real provider capacity reservation, live pricing, shipping, courier booking, provider SLA acceptance, payout settlement, machine telemetry or certified engineering approval.

It creates the governance layer needed before those integrations are activated.

## Local verification

```powershell
cd C:\REBORN\REBORN
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

In a second PowerShell window:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-geometry-printability-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-routing-governance.ps1
```

Prototype console:

```text
http://127.0.0.1:8080/prototype/index.html#/provider-routing
```

Admin demo login:

```text
admin@reborn.local
password
```
