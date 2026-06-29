# Re-born

**Re-born** is a Repair Intelligence Platform designed to allow anyone to repair anything.

Repository ufficiale: https://github.com/gianpaolobol/reborn

---

## Mission

> Allow anyone to repair anything.

Re-born helps people identify broken products and components, find or generate the right repair solution, produce spare parts locally through distributed providers, and learn from every completed repair.

---

## What Re-born is

Re-born is not a generic 3D model marketplace. It is a repair operating system combining:

- AI recognition;
- repair journeys;
- product and component knowledge graph;
- CAD and spare parts marketplace;
- distributed manufacturing providers;
- maker royalties;
- wallet and repair credits;
- enterprise repair intelligence;
- sustainability metrics.

---

## Strategic principle

The user is not looking for an STL file.

The user wants this:

> My object must work again.

Every product, UX, backend and business decision must start from that principle.

---

## Current repository status

This repository is currently in the **Product Operating System phase**.

The goal is to version the strategic, product, UX and architectural foundations before starting implementation.

Current focus:

1. Product Book;
2. PRD;
3. UX Bible;
4. Design System;
5. Architecture;
6. Database and API contracts;
7. Roadmap and investor material.

---

## Documentation map

```text
PRODUCT.md                           # Root product operating document
docs/00-master-index/                # Global index
docs/01-foundation/                  # Principles, glossary, decisions
docs/02-product-book/                # Vision, model, personas, engine, metrics
docs/03-prd/                         # Product requirements and MVP scope
docs/04-ux-bible/                    # UX flows, screens, states, edge cases
docs/05-design-system/               # Visual principles and tokens
docs/07-architecture/                # DDD and application architecture
docs/08-database/                    # Database and knowledge graph schema
docs/09-api/                         # API contracts
docs/10-security/                    # Security baseline
docs/14-roadmap/                     # Roadmap
docs/17-handoff/                     # Agent handoff documents
```

---

## Tech direction

Initial stack:

- PHP 8.3+;
- HTML5;
- CSS3;
- Vanilla JavaScript;
- SQLite for development;
- MariaDB/MySQL for production;
- Clean Architecture;
- DDD;
- Repository Pattern;
- Domain Events;
- Service Layer.

Frontend frameworks are intentionally avoided during the first phase.

---

## Development order

Do not start from implementation.

The correct order is:

1. Product Book;
2. PRD;
3. UX Bible;
4. Design System;
5. Wireframes;
6. UI mockups;
7. Prototype;
8. Backend;
9. Frontend.

---

## GitHub setup

If the local commit already exists but the push failed because of authentication:

```powershell
winget install --id GitHub.cli
gh auth login
git push -u origin main
```

If the remote is missing:

```powershell
git remote add origin https://github.com/gianpaolobol/reborn.git
git branch -M main
git push -u origin main
```

---

## Step 3 — MVP Delivery Pack

This repository now includes the first execution layer of the Re-born OS:

- locked MVP boundaries;
- epics and ticket-ready user stories;
- priority backlog;
- textual wireframes for the repair journey;
- API route map and endpoint contract;
- database implementation plan;
- sprint plan and release criteria;
- GitHub issue templates and a script to create issues with GitHub CLI.

The next product phase is **designing the clickable prototype** from the MVP wireframes before writing production backend code.

## Static MVP prototype

The first navigable MVP prototype is available at:

```text
public/prototype/index.html
```

Open it directly in a browser. It uses only HTML5, CSS3 and Vanilla JavaScript.

Main prototype routes:

```text
#/start
#/capture
#/diagnosis
#/repair-paths
#/provider-network
#/checkout
#/account
#/provider
#/maker
#/enterprise
#/admin-ops
```

## Step 5 — Backend Skeleton

The repository now includes a first PHP 8.3 backend skeleton for the MVP API.

```powershell
php scripts/doctor.php
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Open:

```text
http://127.0.0.1:8080/api/health
http://127.0.0.1:8080/prototype/index.html
```

Core endpoints:

```text
GET  /api/health
GET  /api/v1/repair-cases
POST /api/v1/repair-cases
POST /api/v1/repair-cases/{id}/diagnose
GET  /api/v1/providers
GET  /api/v1/knowledge/nodes
```

## Step 6 — Prototype API Integration

The MVP prototype is now API-aware. Run the backend with:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open:

```text
http://127.0.0.1:8080/prototype/index.html
```

If the prototype is opened directly from disk, it automatically uses mock data.



## Current delivery step

**Step 7 — Backend Persistence Hardening** is included.

New backend capabilities:

- uniform JSON error model with `meta.request_id`;
- malformed JSON detection;
- stronger repair-case validation;
- attachment upload persistence for repair photos/CAD/PDF evidence;
- `repair_attachments` and `audit_log` tables;
- domain-events inspection endpoint;
- backend feature/smoke test scripts.

Run locally:

```powershell
php scripts/doctor.php
php scripts/setup-dev.php
php scripts/run-feature-tests.php
php -S 127.0.0.1:8080 -t public public/index.php
```


## Step 8 — Identity & Access MVP

The MVP backend now includes the first authentication and authorization layer:

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- bearer token sessions stored hashed in SQLite
- role model: `repair_user`, `maker`, `provider`, `enterprise`, `admin`
- admin-only route examples: `/api/v1/admin/users`, `/api/v1/domain-events`

Demo accounts after `php scripts/setup-dev.php`:

| Role | Email | Password |
|---|---|---|
| repair_user | repair.user@reborn.local | password |
| maker | maker@reborn.local | password |
| provider | provider@reborn.local | password |
| enterprise | enterprise@reborn.local | password |
| admin | admin@reborn.local | password |

Run the identity smoke test with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
```


## Step 9 — Repair Case Ownership & User Dashboards

The backend now supports authenticated repair case ownership and role dashboards. Run `scripts/smoke-ownership-dashboards.ps1` after starting the PHP dev server to validate repair user ownership, admin dashboard preview and provider restrictions.

## Step 10 — Prototype Auth UI & Role Dashboards

The prototype now includes a browser login screen, demo role switching, token persistence, logout and role-aware dashboards.

Run:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Open:

```text
http://127.0.0.1:8080/prototype/index.html#/login
```

Smoke tests:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
```

## Step 21 — Observability Dashboard, Backup Automation & Deployment Runbook v1

Step 21 makes the local MVP operable and governable beyond a pure demo.

New capabilities:

- admin observability console in the prototype at `#/observability`;
- HTTP request metrics persisted in SQLite;
- admin log viewer for API exception logs;
- readiness snapshot history;
- manual SQLite backup creation through API/UI;
- backup status and restore checklist;
- deployment runbook and smoke-test run order endpoints.

Run after starting the PHP server:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-observability-ops.ps1
```

Open:

```text
http://127.0.0.1:8080/prototype/index.html#/observability
```

Login as `admin@reborn.local / password` to access the protected operational endpoints.

## Step 22 — Incident Response, Alerting & Status Management v1

Step 22 turns Step 21 observability into an operator workflow for pilot/beta governance.

It adds:

- SQLite-backed alert rules and alert lifecycle;
- incident lifecycle and status updates;
- public local/pilot status payload at `/api/status`;
- maintenance windows;
- admin prototype console at `#/incidents`;
- smoke test `scripts/smoke-incident-response-status.ps1`.

Run after Step 20/21 checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-incident-response-status.ps1
```

Open the admin console:

```text
http://127.0.0.1:8080/prototype/index.html#/incidents
```

## Step 23 — Notification Center & Escalation Workflow v1

Step 23 connects observability and incident response to auditable operator action.

It adds:

- SQLite-backed notification channels;
- notification rules mapped to alerts, incidents, status updates and maintenance windows;
- mock notification delivery records with queued/sent/failed/cancelled lifecycle;
- escalation policies for incident severity levels;
- escalation runs linked to incidents;
- admin prototype console at `#/notifications`;
- smoke test `scripts/smoke-notification-escalation.ps1`.

Important: Step 23 does **not** send real email, SMS, Slack or webhook messages. It creates auditable local/mock delivery records so the pilot workflow is testable without pretending external transports are production-ready.

Run after Step 20/21/22 checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-notification-escalation.ps1
```

Open the admin console:

```text
http://127.0.0.1:8080/prototype/index.html#/notifications
```

## Step 24 — Service Level & Operational Governance v1

Step 24 connects operations to measurable commitments and pilot governance.

It adds:

- SQLite-backed SLA policies for alerts and incidents;
- SLA evaluations with response and resolution due dates;
- manual response and resolution evidence for SLA records;
- operational policies for pilot readiness, incident comms, backup/restore, provider quality and upload-data handling;
- policy attestations;
- admin prototype console at `#/service-governance`;
- smoke test `scripts/smoke-service-governance-sla.ps1`.

This is not a legal SLA contract yet. It is a local/pilot governance layer to prove Re-born can be operated responsibly before onboarding real users/providers.

Run after Step 20/21/22/23 checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-service-governance-sla.ps1
```

Open the admin console:

```text
http://127.0.0.1:8080/prototype/index.html#/service-governance
```

## Step 25 — Privacy, Consent & Data Governance v1

Step 25 adds the first local/pilot privacy governance layer needed before a credible beta.

It adds:

- SQLite-backed privacy notices;
- consent records with grant/withdraw lifecycle;
- data processing inventory for repair, AI learning, provider governance and platform ops;
- retention rules and dry-run retention evaluations;
- data subject request workflow;
- local JSON subject-access exports stored in SQLite;
- admin prototype console at `#/privacy-governance`;
- smoke test `scripts/smoke-privacy-data-governance.ps1`.

Important: Step 25 is **not** final GDPR/legal approval. It makes privacy, consent, retention and data-subject workflows visible and auditable in the local/pilot MVP without pretending that external AI, payments or notification providers are production-approved.

Run after Step 20/21/22/23/24 checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-privacy-data-governance.ps1
```

Open the admin console:

```text
http://127.0.0.1:8080/prototype/index.html#/privacy-governance
```

## Step 26 — Beta Release Management & Pilot Readiness v1

Step 26 turns operational/privacy governance into controlled beta readiness.

It adds:

- SQLite-backed feature flags for safe pilot rollout controls;
- release records with release status, target environment, risk level and decision history;
- release gate evaluation using local readiness, backup, incident, SLA, privacy and feature-flag evidence;
- pilot cohorts for repair users, maker/providers and enterprise design partners;
- pilot participant records with consent and onboarding state;
- admin prototype console at `#/release-management`;
- smoke test `scripts/smoke-beta-release-management.ps1`.

Important: Step 26 does **not** make Re-born production-grade. It creates a local/pilot release-control layer so risky features such as real AI, real payments and maker economy can remain disabled until their privacy, legal, security and cost controls are ready.

Run after Step 20/21/22/23/24/25 checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-beta-release-management.ps1
```

Open the admin console:

```text
http://127.0.0.1:8080/prototype/index.html#/release-management
```

## Step 27 — Enterprise & Partner Onboarding Governance v1

Step 27 connects beta release control to a governed partner ecosystem.

Added:

- partner accounts for providers, makers and enterprise design partners;
- onboarding tasks with required evidence;
- partner agreements and pilot terms records;
- manual/API/email/webhook integration records;
- partner readiness reviews and readiness scores;
- admin prototype console `#/partner-onboarding`;
- readiness check `partner_onboarding`;
- smoke test `scripts/smoke-partner-onboarding-governance.ps1`.

Important: Step 27 does **not** create legally binding contracts, production partner APIs, provider payouts or maker royalties. It creates the governance records needed to decide which partners can safely participate in a local/pilot beta.

Run after setup and server start:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-partner-onboarding-governance.ps1
```


## Step 28 — Marketplace Revenue, Credits & Payout Governance v1

Adds fee policies, repair credit accounts, credit ledger transactions, payout accounts, mock payout runs, payout items, revenue audit log and the prototype admin console `#/marketplace-revenue`. Real payment settlement, tax documents, KYC/KYB and production payouts remain intentionally out of scope.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-marketplace-revenue-governance.ps1
```

## Step 29 — Maker Economy, Model Licensing & Repair Bounties v1

Step 29 connects the maker economy to the Repair Journey without turning Re-born into a generic STL marketplace.

It adds:

- maker profiles connected to repair credit accounts;
- governed repair model assets with status, license, category, quality score and safety notes;
- model licenses for pilot repair use and future commercial review;
- controlled model download records;
- local maker royalty events posted as repair credits;
- repair bounties for real object repair problems;
- bounty submissions, review and credit-award workflow;
- maker economy audit log;
- admin prototype console `#/maker-economy`;
- readiness check `maker_economy`;
- smoke test `scripts/smoke-maker-economy-governance.ps1`.

Important: Step 29 does **not** publish a public model marketplace, does not deliver real files, does not pay cash royalties and does not replace legal/IP review. It creates the local/pilot governance layer for makers, licenses, credit royalties and repair bounties.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-maker-economy-governance.ps1
```

Open the admin console:

```text
http://127.0.0.1:8080/prototype/index.html#/maker-economy
```


## Step 30 — AI Pipeline Governance & Human-in-the-Loop Review v1

Adds AI provider registry, AI pipeline run ledger, human review workflow, dataset governance, AI safety rules, quality evaluations and an admin prototype console at `#/ai-governance`. This remains a local/pilot governance layer: no real external AI provider, STL generation or training job is enabled yet.

## Step 31 — AI Provider Adapter Sandbox & Job Orchestration v1

Step 31 prepares Re-born for future Meshy, Trellis, Rodin or internal AI worker integrations without enabling unsafe live calls.

It adds:

- sandbox AI provider adapters;
- adapter health checks;
- AI orchestration job queue;
- job lifecycle and retry/cancel workflow;
- job events timeline;
- artifact stubs for future generated outputs;
- provider cost ledger for reserved/spent mock costs;
- AI provider sandbox audit log;
- admin prototype console `#/ai-provider-sandbox`;
- readiness check `ai_provider_sandbox`;
- smoke test `scripts/smoke-ai-provider-sandbox-orchestration.ps1`.

Important: Step 31 does **not** call Meshy, Trellis, Rodin or any external AI provider. It does not generate real STL/CAD files. It creates the local/pilot orchestration and governance layer required before real AI integrations are enabled.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-provider-sandbox-orchestration.ps1
```

Open the admin console:

```text
http://127.0.0.1:8080/prototype/index.html#/ai-provider-sandbox
```

## Step 32 — CAD/Geometry Validation & Printability Governance v1

Step 32 adds a pilot geometry governance layer: CAD/mesh asset registry, validation profiles, printability rules, validation runs, findings, human review items and audit log.

Prototype route:

```text
/prototype/index.html#/geometry-printability
```

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-geometry-printability-governance.ps1
```

This step does not run a real CAD kernel or slicer. It records the governance workflow needed before AI-generated or uploaded geometry can be routed to providers, maker publication or repair orders.

### Step 33 — Provider Capability, Machine Profile & Fulfilment Routing Governance v1

Step 33 adds provider capability profiles, machine profiles, routing policies, fulfilment routing requests, provider routing matches, human routing review and provider routing audit logging.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-routing-governance.ps1
```

Prototype route:

```text
/prototype/index.html#/provider-routing
```


### Step 34 — Fulfilment Dispatch, Shipment Tracking & Proof-of-Repair Governance v1

Step 34 adds dispatch policies, fulfilment dispatch records, local/mock shipment tracking events, proof-of-repair records, dispatch review items and dispatch audit logging.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-dispatch-proof-governance.ps1
```

Prototype route:

```text
/prototype/index.html#/dispatch-governance
```

This step does not book real couriers or create real shipment labels. It records the governance workflow needed before routed repair fulfilments can be operated in a pilot or beta environment.
