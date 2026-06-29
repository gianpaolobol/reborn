# Step 36 — Sustainability Impact, Circularity Metrics & Repair Outcome Intelligence v1

## Objective

Step 36 makes Re-born able to govern sustainability and circularity evidence from the Repair Journey.

The goal is not to publish environmental claims yet. The goal is to create a local/pilot layer that can answer:

- how many objects were saved;
- how many accepted repairs have impact evidence;
- what CO₂e, waste and material savings are estimated;
- which factors were used;
- which claims require review;
- which repair outcomes create actionable intelligence.

This keeps Re-born aligned with the product principle: users do not look for STL files, they want broken objects to work again.

## Added capabilities

- Sustainability factor registry.
- Repair impact records linked to acceptance/proof/dispatch evidence when available.
- Pilot impact calculation for CO₂e avoided, waste diverted, material saved and repair score.
- Circularity metric snapshots.
- Repair outcome insights.
- Human impact review queue.
- Sustainability audit log.
- Prototype console: `#/sustainability-impact`.
- Readiness check: `sustainability_impact`.
- Smoke test: `scripts/smoke-sustainability-impact-circularity.ps1`.

## New API endpoints

```text
GET  /api/v1/platform/sustainability-impact
GET  /api/v1/platform/sustainability-factors
GET  /api/v1/platform/repair-impact-records
POST /api/v1/platform/repair-impact-records
POST /api/v1/platform/repair-impact-records/{id}/calculate
GET  /api/v1/platform/circularity-snapshots
POST /api/v1/platform/circularity-snapshots
GET  /api/v1/platform/repair-outcome-insights
POST /api/v1/platform/repair-outcome-insights/evaluate
GET  /api/v1/platform/impact-review-items
POST /api/v1/platform/impact-review-items/{id}/review
GET  /api/v1/platform/sustainability-audit-log
```

## Scope limits

Step 36 does **not** provide:

- certified lifecycle assessment;
- legally approved public environmental claims;
- ESG reporting;
- official CO₂ accounting;
- third-party verified sustainability methodology;
- real product-specific emission factors;
- public marketing copy.

All values are local/pilot estimates until methodology, factor sources and legal language are reviewed.

## Smoke test

With the development server running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-sustainability-impact-circularity.ps1
```

## Recommended sequence

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-customer-care-warranty-support.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-sustainability-impact-circularity.ps1
```

## Commit suggestion

```powershell
git status
git add .
git commit -m "platform: add sustainability impact and circularity metrics v1"
git push
```
