# STEP 27 — Enterprise & Partner Onboarding Governance v1

## Goal

Step 27 turns Re-born's beta release layer into a governed pilot ecosystem. After readiness, observability, incidents, notifications, SLA, privacy and release gates, the platform must also know which providers, makers and enterprise partners can safely participate in a controlled beta.

This step is intentionally not a public marketplace launch. It creates the operating controls needed before real providers, makers or enterprise design partners are exposed to production-like workflows.

## Added capabilities

- Partner accounts for providers, makers, enterprise partners and public-sector partners.
- Partner onboarding tasks with required/optional status and evidence.
- Partner agreements for pilot terms, data processing, provider terms and future maker/IP terms.
- Partner integrations for manual, email, webhook or API workflows.
- Partner readiness reviews with pilot gates and readiness scores.
- Admin prototype console at `#/partner-onboarding`.
- Smoke test: `scripts/smoke-partner-onboarding-governance.ps1`.

## New database migration

`database/migrations/021_partner_onboarding_enterprise_governance.sql`

Tables:

- `platform_partner_accounts`
- `platform_partner_onboarding_tasks`
- `platform_partner_agreements`
- `platform_partner_integrations`
- `platform_partner_readiness_reviews`

Seeded pilot records include:

- Bologna Maker Lab as a provider pilot.
- Enterprise Design Partner as a strategic enterprise discovery partner.
- Community Maker Pilot as a maker-economy precursor.

## New service

`src/Platform/Application/PartnerOnboardingService.php`

Responsibilities:

- Build the partner onboarding dashboard.
- Create partner accounts.
- Create default onboarding tasks and agreements.
- Update onboarding task status.
- Accept or update partner agreements.
- Create and test partner integrations.
- Evaluate partner readiness gates.
- Maintain partner readiness score.

## New API endpoints

```text
GET  /api/v1/platform/partner-onboarding
GET  /api/v1/platform/partners
POST /api/v1/platform/partners
GET  /api/v1/platform/partners/{id}/readiness
POST /api/v1/platform/partners/{id}/readiness/evaluate
GET  /api/v1/platform/partner-tasks
POST /api/v1/platform/partner-tasks/{id}/status
GET  /api/v1/platform/partner-agreements
POST /api/v1/platform/partners/{id}/agreements
POST /api/v1/platform/partner-agreements/{id}/status
GET  /api/v1/platform/partner-integrations
POST /api/v1/platform/partners/{id}/integrations
POST /api/v1/platform/partner-integrations/{id}/status
GET  /api/v1/platform/partner-readiness-reviews
```

All endpoints are admin-only except the general health/readiness endpoints.

## Readiness gates

Partner readiness evaluates:

1. Required onboarding tasks completed or waived.
2. At least one relevant agreement accepted.
3. Pilot-safe integration or manual workflow available.
4. Production boundary note for enterprise partners.

Possible review statuses:

- `ready_for_pilot`
- `conditional`
- `blocked`

## What this step does not do

Step 27 does **not** create a real legal contract system, real external API integrations, provider payouts, enterprise SSO, or maker royalty payments. Those remain future steps.

It creates a local/pilot governance layer so the project can demonstrate that partner participation is controlled, reviewed and traceable.

## Local verification

With server running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-partner-onboarding-governance.ps1
```

Recommended full run after Step 27:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-observability-ops.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-incident-response-status.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-notification-escalation.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-service-governance-sla.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-privacy-data-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-beta-release-management.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-partner-onboarding-governance.ps1
```

## Commit suggestion

```powershell
git status
git add .
git commit -m "platform: add partner onboarding and enterprise governance v1"
git push
```
