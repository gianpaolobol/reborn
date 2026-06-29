# STEP 34 — Fulfilment Dispatch, Shipment Tracking & Proof-of-Repair Governance v1

## Goal

Step 34 connects provider routing to pilot fulfilment operations.

The product principle remains unchanged: the user is not looking for an STL; the user wants the object to work again. After geometry validation and provider routing, Re-born now needs an auditable way to dispatch the repair, track the local/mock shipment or pickup, and collect proof that the repair was actually completed.

## Implemented scope

- Dispatch policies.
- Fulfilment dispatch records generated from provider routing matches.
- Dispatch lifecycle: planned, approved, dispatched, in transit, delivered, proof review, proof accepted, completed or blocked.
- Shipment/pickup tracking events.
- Proof-of-repair records with structured evidence and quality score.
- Human review queue for dispatch exceptions and proof issues.
- Dispatch audit log.
- Admin prototype console at `#/dispatch-governance`.
- Readiness check `dispatch_governance`.
- Smoke test `scripts/smoke-dispatch-proof-governance.ps1`.

## API endpoints

```text
GET  /api/v1/platform/dispatch-governance
GET  /api/v1/platform/dispatch-policies
GET  /api/v1/platform/dispatches
POST /api/v1/platform/dispatches
POST /api/v1/platform/dispatches/{id}/advance
GET  /api/v1/platform/shipment-events
POST /api/v1/platform/dispatches/{id}/shipment-events
GET  /api/v1/platform/proof-of-repair-records
POST /api/v1/platform/dispatches/{id}/proof-of-repair
POST /api/v1/platform/proof-of-repair-records/{id}/review
GET  /api/v1/platform/dispatch-review-items
POST /api/v1/platform/dispatch-review-items/{id}/review
GET  /api/v1/platform/dispatch-audit-log
```

## What remains mock or deferred

Step 34 does **not** implement real courier booking, shipping labels, pickup scheduling, parcel insurance, return logistics, customs, real provider acceptance, customer signature, real tracking webhooks or final legal warranty handling.

It creates the local/pilot governance layer needed before those integrations are activated.

## Local verification

```powershell
cd C:\REBORN\REBORN
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

In a second PowerShell window:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-routing-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-dispatch-proof-governance.ps1
```

Prototype console:

```text
http://127.0.0.1:8080/prototype/index.html#/dispatch-governance
```

Admin demo login:

```text
admin@reborn.local
password
```
