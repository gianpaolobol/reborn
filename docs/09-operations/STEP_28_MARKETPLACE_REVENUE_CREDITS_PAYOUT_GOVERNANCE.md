# STEP 28 — Marketplace Revenue, Credits & Payout Governance v1

## Objective

Step 28 creates the first governance layer for Re-born marketplace monetization without enabling real money movement.

It adds a local/pilot foundation for:

- marketplace fee policies;
- repair credit accounts;
- credit ledger transactions;
- provider/maker payout accounts;
- mock payout runs;
- payout items;
- revenue audit log;
- an admin prototype console at `#/marketplace-revenue`.

## Product principle

Re-born is still a Repair Intelligence Platform, not a generic STL marketplace.

The revenue layer therefore exists to support the Repair Journey:

1. repair order value can be understood;
2. platform fees can be reviewed;
3. provider/maker incentives can be tested;
4. credits can reward useful repair contributions;
5. payouts can be governed before real payment rails are connected.

## What is intentionally not included

Step 28 does **not** implement:

- real Stripe/PayPal settlement;
- provider bank payouts;
- maker cash royalties;
- invoices or tax documents;
- automated refunds;
- KYC/KYB;
- public maker marketplace downloads;
- production accounting.

All payout operations are marked as mock/manual governance records.

## Main API endpoints

- `GET /api/v1/platform/marketplace-revenue`
- `GET /api/v1/platform/marketplace-fee-policies`
- `GET /api/v1/platform/credit-accounts`
- `POST /api/v1/platform/credit-accounts`
- `GET /api/v1/platform/credit-transactions`
- `POST /api/v1/platform/credit-transactions`
- `GET /api/v1/platform/payout-accounts`
- `POST /api/v1/platform/payout-accounts`
- `GET /api/v1/platform/payout-runs`
- `POST /api/v1/platform/payout-runs/evaluate`
- `POST /api/v1/platform/payout-runs/{id}/approve`
- `POST /api/v1/platform/payout-runs/{id}/paid`
- `GET /api/v1/platform/payout-items`
- `GET /api/v1/platform/revenue-audit-log`

## Smoke test

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-marketplace-revenue-governance.ps1
```

Recommended sequence:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-observability-ops.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-incident-response-status.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-notification-escalation.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-service-governance-sla.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-privacy-data-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-beta-release-management.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-partner-onboarding-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-marketplace-revenue-governance.ps1
```

## Readiness impact

`/api/ready` now includes a `marketplace_revenue` check.

The check remains warning-only if tables are missing or not seeded. It should not block local usage as a hard production failure, because real monetization is intentionally not enabled yet.
