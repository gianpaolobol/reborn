# Step 29 — Maker Economy, Model Licensing & Repair Bounties v1

## Objective

Step 29 introduces a governed maker-economy layer that supports the Repair Journey without reducing Re-born to a marketplace of STL files.

The product principle remains: the user does not want a file; the user wants the broken object to work again.

## Scope

Implemented in this step:

- maker profiles;
- repair model assets;
- model licenses;
- controlled model download records;
- local maker royalty events in repair credits;
- repair bounties;
- bounty submissions and review;
- maker economy audit log;
- admin prototype route `#/maker-economy`;
- smoke test `scripts/smoke-maker-economy-governance.ps1`.

## New migration

```text
database/migrations/023_maker_economy_model_licensing_repair_bounties.sql
```

New tables:

```text
platform_maker_profiles
platform_model_assets
platform_model_licenses
platform_model_downloads
platform_model_royalty_events
platform_repair_bounties
platform_bounty_submissions
platform_maker_economy_audit_log
```

## New service

```text
src/Platform/Application/MakerEconomyService.php
```

The service handles:

- maker onboarding records;
- model asset submission and review;
- pilot download recording;
- royalty-credit event posting;
- repair bounty creation;
- bounty submission acceptance and credit award;
- audit trail creation.

## New API endpoints

```text
GET  /api/v1/platform/maker-economy
GET  /api/v1/platform/maker-profiles
POST /api/v1/platform/maker-profiles
POST /api/v1/platform/maker-profiles/{id}/status
GET  /api/v1/platform/model-assets
POST /api/v1/platform/model-assets
POST /api/v1/platform/model-assets/{id}/review
GET  /api/v1/platform/model-licenses
GET  /api/v1/platform/model-downloads
POST /api/v1/platform/model-downloads
GET  /api/v1/platform/model-royalty-events
GET  /api/v1/platform/repair-bounties
POST /api/v1/platform/repair-bounties
GET  /api/v1/platform/bounty-submissions
POST /api/v1/platform/bounty-submissions
POST /api/v1/platform/bounty-submissions/{id}/review
GET  /api/v1/platform/maker-economy-audit-log
```

## Readiness

`/api/ready` now includes the `maker_economy` check.

The check verifies that the Step 29 tables exist and contain enough seed data to demonstrate:

- at least one maker profile;
- at least one model asset;
- at least one model license;
- at least one open or in-review repair bounty.

It is a governance/readiness signal, not a production IP/legal clearance.

## Prototype

Admin console:

```text
/prototype/index.html#/maker-economy
```

The console shows:

- active makers;
- approved model assets;
- royalty credits;
- open repair bounties;
- maker profile table;
- repair model asset table;
- license table;
- controlled downloads;
- royalty events;
- bounty submissions;
- maker economy audit trail.

## Smoke test

Run after Step 20–28 tests:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-maker-economy-governance.ps1
```

The smoke test verifies:

1. health capabilities;
2. readiness check;
3. admin authentication;
4. dashboard access;
5. license listing;
6. maker profile creation and activation;
7. model submission and approval;
8. model download and royalty-credit event;
9. repair bounty creation;
10. bounty submission and acceptance;
11. audit log.

## Explicit non-goals

Step 29 does not implement:

- public STL/model marketplace;
- real CAD/STL file hosting;
- cash royalty settlement;
- VAT/tax documents;
- IP ownership verification;
- KYC/KYB;
- public maker profile pages;
- production model moderation;
- automated AI model generation;
- legal licensing approval.

## Product guardrail

Maker economy exists to make real repairs possible.

A model should be treated as repair evidence and repair capability, not as the primary product.
