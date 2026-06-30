# Re-born

[![Re-born Smoke Tests](https://github.com/gianpaolobol/reborn/actions/workflows/smoke-tests.yml/badge.svg)](https://github.com/gianpaolobol/reborn/actions/workflows/smoke-tests.yml)

**Re-born** is a Repair Intelligence Platform designed to allow anyone to repair anything.

Repository ufficiale: https://github.com/gianpaolobol/reborn

---

## Continuous Integration

### CI auth preflight

The smoke-test workflow resets deterministic demo credentials before running the API smoke suite:

```bash
php scripts/reset-demo-credentials.php
php scripts/verify-demo-credentials.php
```

It then runs an HTTP auth preflight against `/api/v1/auth/login` before the full smoke suite starts. If the admin login fails, check the uploaded runtime artifact `reborn-ci-runtime-logs`, especially `ci-auth-preflight-failure.json`.


Re-born includes a GitHub Actions smoke test pipeline:

```text
.github/workflows/smoke-tests.yml
```

The CI workflow runs on push to `main`, pull requests to `main` and manual `workflow_dispatch`. It uses PHP 8.4 with `pdo_sqlite` and `sqlite3`, creates a local SQLite database, starts the PHP built-in server and runs the full smoke regression suite through:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\ci-smoke-tests.ps1 -BaseUrl http://127.0.0.1:8080
```

Future steps that add smoke tests must update `scripts/ci-smoke-tests.ps1`.

### Release evidence and quality gate

Step 39 adds release evidence on top of the smoke suite. After the smoke tests run, CI generates:

```text
storage/logs/ci-regression-test-matrix.json
storage/logs/ci-release-evidence.json
storage/logs/ci-quality-gate.json
storage/logs/ci-release-evidence.md
```

GitHub Actions uploads them as the `reborn-ci-release-evidence` artifact. The matrix maps the 33 smoke tests to steps, bounded contexts, strategic assets and release gates.

Local command after the server and smoke suite are running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\ci-release-evidence.ps1 -BaseUrl http://127.0.0.1:8080
```

Future steps that add smoke coverage must update both `scripts/ci-smoke-tests.ps1` and `scripts/ci-release-evidence.ps1`.

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
php scripts/verify-demo-credentials.php # optional CI/local check for demo accounts
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


### Step 35 — Customer Acceptance, Warranty & Post-Repair Support Governance v1

Step 35 closes the post-repair loop after proof-of-repair. It adds customer acceptance records, customer decision workflow, warranty policy placeholders, warranty cases, post-repair support tickets, customer feedback records, post-repair review items and customer care audit logging.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-customer-care-warranty-support.ps1
```

Prototype route:

```text
/prototype/index.html#/customer-care
```

This step does not create legal warranty terms, refunds, real CRM tickets or external customer notifications. It records the governance workflow needed before beta customer commitments can be handled safely.

### Step 36 — Sustainability Impact, Circularity Metrics & Repair Outcome Intelligence v1

Step 36 turns accepted repairs and proof-of-repair evidence into local/pilot impact records, circularity snapshots and repair outcome insights. It adds sustainability factors, calculated impact records, internal circularity reporting, impact review items and sustainability audit logging.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-sustainability-impact-circularity.ps1
```

Prototype route:

```text
/prototype/index.html#/sustainability-impact
```

This step does not create certified LCA reports, legal environmental claims, ESG reporting or public sustainability statements. It records the governance workflow and pilot estimates needed before external impact claims can be reviewed and validated.

### Step 37 — Investor Demo, KPI Narrative & Board Reporting Governance v1

Step 37 turns the broad local MVP into an investor/board-ready operating narrative. It adds governed KPI definitions, KPI snapshots, demo narrative sections, board reports, evidence records, investor demo readiness reviews and investor reporting audit logging.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-investor-reporting-board-readiness.ps1
```

Prototype route:

```text
/prototype/index.html#/investor-reporting
```

This step does not create audited financials, legal investment materials, certified ESG reports or public traction claims. It records local/pilot evidence, caveats and narrative governance so Re-born can be demonstrated honestly before beta and fundraising use.

### Step 38 — Continuous Integration Smoke Test Pipeline v1

Step 38 adds GitHub Actions CI for the Re-born smoke suite. The workflow sets up PHP 8.4 with `pdo_sqlite` and `sqlite3`, copies `.env.ci.example`, runs `php scripts/setup-dev.php`, starts the PHP built-in server, waits for `/api/health` and runs the full smoke regression suite via `scripts/ci-smoke-tests.ps1`.

Workflow:

```text
.github/workflows/smoke-tests.yml
```

Local CI-equivalent smoke runner:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\ci-smoke-tests.ps1 -BaseUrl http://127.0.0.1:8080
```

This step does not deploy Re-born. It adds repeatable verification so future steps can be validated on GitHub instead of relying only on the local Windows environment or assistant sandbox.


### CI auth hardening note

The GitHub Actions smoke pipeline now resets and verifies the deterministic demo credentials inside `scripts/ci-smoke-tests.ps1` immediately before the full smoke suite. The CI environment also enables `DEMO_AUTH_FALLBACK_ENABLED=true` for the five demo accounts only; keep this disabled in production.


### Runtime verification V4

The CI runtime check now uses `scripts/ci-verify-runtime.php` instead of inline `php -r` commands. The workflow log must show `STEP38_RUNTIME_SCRIPT_VERIFY_V5`. If a run still fails with a PHP command-line parse error, the run is using an older workflow commit.

## Step 40 — Demo Mode, Guided Repair Journey & Investor Walkthrough v1

Step 40 adds a guided demo layer for investor and beta/operator walkthroughs. It includes demo modes, guided repair journey steps, demo sessions, session events, feedback, readiness reviews, audit log, a prototype console at `#/demo-walkthrough`, and a CI smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-demo-walkthrough-investor-journey.ps1
```

The full CI suite now runs 31 smoke tests and the release evidence matrix includes Step 40 as a release-blocking demo governance gate.

## Step 41 — Demo Data Room, Pilot Launch Pack & Stakeholder Feedback Loop v1

Step 41 turns the guided demo into an external-stakeholder preparation layer. It adds a demo data room asset registry, pilot launch checklist, stakeholder feedback loops, post-demo reports, pilot go/no-go decisions, pilot launch audit logging, a prototype console at `#/pilot-launch`, and a CI smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-demo-data-room-pilot-feedback-loop.ps1
```

The full CI suite now runs 32 smoke tests and the release evidence matrix includes Step 41 as a release-blocking pilot governance gate. This step does not approve production launch, real payments, real fulfilment, legal warranty terms, or public sustainability claims. It prepares controlled stakeholder demos and private-pilot decision evidence.

## Step 42 — Public Pilot Demo, Partner Intake & Real-World Validation Pack v1

Step 42 turns the Step 41 internal pilot launch pack into a controlled external-pilot surface. It adds public pilot demo pages, a no-auth external pilot intake endpoint, admin triage for partner/provider/maker/repair-user leads, stakeholder lead scoring, real-world validation cases, public pilot evaluation and audit logging. The prototype console is available at `#/public-pilot` and the safe public endpoint is available at `/api/v1/public-pilot-demo`.

Smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-public-pilot-real-world-validation.ps1
```

The full CI suite now runs 33 smoke tests and the release evidence matrix includes Step 42 as a release-blocking real-world validation gate. This step does not approve production launch, provider activation, real payments, payouts, logistics, warranty terms or public success/sustainability claims. It collects external interest and converts it into governed pilot evidence.


## Step 43 — Guided User Repair Experience Simplification v1

Step 43 simplifies the prototype for first-time repair users. The default route now opens a guided repair experience at `#/repair-guide`, the top navigation is reduced to the essential user path, and advanced governance/investor/operator consoles are grouped under `#/advanced` instead of overwhelming the main journey.

The Step 43 UX rule is: normal users get one linear repair path; operators still get full governance depth, but outside the primary navigation.

New/updated prototype routes:

```text
#/repair-guide
#/advanced
#/overview
```

New smoke test:

```text
scripts/smoke-guided-user-repair-experience.ps1
```

The full CI suite now runs 34 smoke tests and the release evidence matrix includes Step 43 as a release-blocking user activation gate. This step does not remove governance modules; it reorganises them so the product is understandable before it is impressive.

## Step 44 — FixPart Benchmark, Repair-First Offer Architecture & Replacement-Part Wizard v1

Step 44 repositions the first-time user experience around the clearest commercial promise: the user does not need to know the spare-part code, catalogue category, material or CAD format. Re-born starts from the broken component and guides the user toward a replacement part.

The public/user-facing flow is reduced to four visible steps:

```text
1. Problem
2. Photos & files
3. Generate part
4. Quote
```

This step translates the FixPart benchmark into product strategy: existing spare-parts catalogues are strong when the user already knows the exact model/code; Re-born differentiates by starting earlier, identifying the part, checking whether it exists, and guiding generation/production when it does not.

Updated prototype route:

```text
#/repair-guide
```

New smoke test:

```text
scripts/smoke-repair-first-offer-architecture.ps1
```

The full CI suite now runs 35 smoke tests and the release evidence matrix includes Step 44 as a release-blocking user activation and differentiation gate. This step does not add real AI/CAD generation yet; it makes the offer, navigation and demo flow understandable for non-expert users before deeper integrations are activated.

## Step 45 — AI Photo Recognition, Replacement-Part Brief & Guided Missing Inputs v1

Step 45 connects the Step 44 photo/file moment to a configurable AI photo-recognition provider. After the Step 45.3 UX hotfix, the user sees one primary action: **Carica foto e identifica il pezzo**. Selecting a photo automatically uploads it and starts AI recognition. The result is intentionally simple: either the probable replacement part is recognized, or Re-born asks for the minimum additional images needed to identify it.

The first provider integration is OpenAI Vision through the Responses API, with deterministic fallback when no API key is configured. This keeps local development and CI stable while allowing live AI recognition in an environment where `OPENAI_API_KEY` is set.

Configuration:

```env
AI_PHOTO_RECOGNITION_PROVIDER=openai
AI_PHOTO_RECOGNITION_ENABLED=true
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_VISION_MODEL=gpt-5.5
OPENAI_TIMEOUT_SECONDS=90
OPENAI_VISION_MAX_IMAGES=8
OPENAI_VISION_MAX_IMAGE_BYTES=20971520
OPENAI_VISION_DETAIL=original
OPENAI_VISION_WEB_SEARCH_ENABLED=true
OPENAI_REASONING_EFFORT=high
OPENAI_VISION_MAX_OUTPUT_TOKENS=4500
```

New status endpoint:

```text
GET /api/v1/ai/photo-recognition/status
```

New smoke test:

```text
scripts/smoke-ai-photo-recognition-replacement-brief.ps1
```

The full CI suite now runs 36 smoke tests and the release evidence matrix includes Step 45 as a release-blocking AI/user-activation gate. This step does not approve automatic manufacturing: AI recognition remains preliminary and every generated replacement still requires dimensional, material and human/provider validation before production.


### Step 45.3 — One-Button AI Recognition UX Hotfix

Step 45.3 simplifies the Step 2 user experience for beginners. The previous separate upload and recognition buttons are replaced by a one-button flow: **Carica foto e identifica il pezzo**. The file picker is hidden behind that CTA, and image selection automatically triggers upload plus AI recognition. The result panel now shows only two user-facing outcomes: **pezzo riconosciuto** or **servono altre immagini**.

### Step 45.4 — Adaptive One-Button Recognition & Italian-First Bilingual UX Hotfix

Step 45.4 refines the Step 2 beginner flow after real UI testing. The user still sees one primary action only. When no recognition has been attempted, the button says **Carica foto e identifica il pezzo**. If the AI cannot identify the part with enough confidence, the same button changes to **Carica altre immagini**. No additional “retry” or “upload more” button is shown inside the result panel.

The default interface language is now Italian. A compact IT/EN selector is available in the prototype API banner, with Italian as the default and English as the optional alternative. The user-facing Step 2 copy, recognition outcomes, CTA states and main navigation are localized through a lightweight `REBORN_I18N` dictionary.

User-facing Step 2 outcomes remain intentionally simple:

- **Primo sguardo AI · pezzo riconosciuto** — Re-born shows the probable part, function, manufacturability and next action.
- **Primo sguardo AI · servono altre immagini** — Re-born explains which views are missing and the same main CTA becomes **Carica altre immagini**.

The goal is to keep the repair journey understandable for a non-expert user: one button, one AI answer, one next action.

### Step 45.5 — Reference Image OCR Recognition Hotfix

Step 45.5 fixes a real recognition issue found with product-detail images such as dishwasher rack wheel listings. The AI prompt now treats reference/product images, dimension diagrams and visible text as valid recognition evidence, not as insufficient photos. If a picture contains a part name, part number or dimensions, Re-born extracts them and can mark the part as recognized while still asking for extra images only for manufacturing fit validation.

The recognition JSON now includes:

- `identification.status` — `recognized`, `needs_more_images` or `unclear`;
- `identification.source_image_type` — real broken part, product reference image, dimension diagram or mixed reference images;
- `identification.visible_text` and `identification.part_number`;
- `part_spec.name_it`, `part_spec.known_dimensions` and `part_spec.key_features`.

The UI now shows Italian output such as **Codice pezzo**, **Testo letto nell’immagine**, **Dimensioni lette** and **Caratteristiche visibili**. A product reference image with readable part information no longer forces the CTA into **Carica altre immagini** just because it is not a real broken-part photo.

The debug script also accepts multiple image paths, for example:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-photo-recognition-live.ps1 -BaseUrl http://127.0.0.1:8080 -ImagePath "C:\foto\dettaglio.jpg","C:\foto\misure.jpg"
```

## Step 46 — User Repair Wizard Simplification

Step 46 refocuses the public prototype on a single non-technical journey:

```text
Foto -> Analisi -> Ricambio
```

The default user-facing route is now `#/repair-guide`, rendered by `userRepairWizard` in `public/prototype/assets/js/app.js`. Legacy first-run routes such as `#/start`, `#/capture`, `#/diagnosis`, `#/repair-paths` and `#/provider-network` are intentionally mapped back to the same simplified wizard so a base user is not exposed to provider routing, maker selection, governance, quote engines or admin console concepts.

Visible UX rules:

- Italian remains the default language, with `IT | EN` available.
- The homepage/wizard shows one primary CTA at a time.
- The user starts from one photo and does not need to know the part name.
- AI output is normalized into two clear cases: `Pezzo riconosciuto` or `Servono altre immagini`.
- Missing inputs are minimal and always include a `Non lo so` option.
- Decision, provider matching and quote logic stay behind the scenes.
- Advanced consoles remain available only from `#/advanced`.

Run the dedicated smoke test after starting the PHP dev server:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-user-repair-wizard-simplification.ps1 -BaseUrl http://127.0.0.1:8080
```

The full CI suite now includes this Step 46 smoke script and the release evidence matrix has been extended to Step 46.

### Step 46.1 smoke hotfix — PowerShell 5.1 encoding-safe marker

The Step 45.4 AI photo recognition smoke test now checks the user-facing OCR label with the ASCII-safe partial marker `Testo letto nell` instead of a rigid typographic-apostrophe string. This preserves the UX copy `Testo letto nell’immagine` while avoiding Windows PowerShell 5.1 mojibake such as `nellâ€™immagine` during CI/local smoke execution.


## Step 47 — Maximum Vision Recognition Quality Profile v1

Step 47 upgrades the photo recognition layer from a generic object guess to a maximum-quality replacement-part identification profile. The OpenAI request now sends image inputs with explicit high-fidelity detail, stronger OCR-first instructions, optional Responses API web search for visible part numbers/product names, and a quality retry when the first live answer is too generic.

The intended behavior for product/reference images is no longer “generic plastic cover”. If the image contains a commercial title or part number, Re-born treats that text as primary evidence, reads visible labels and creates a richer Italian brief with:

- exact commercial name when visible;
- part number;
- possible compatible brands/models when supported by visible text or web search;
- visible design features;
- critical dimensions for modelling;
- fastest recommended path: check existing spare first, then maker brief if unavailable.

For example, an image containing `165314 Dishwasher Lower Rack Wheel` should be recognized as a dishwasher lower-rack wheel/roller, not as a generic cover/scocca.

Important: ChatGPT Plus improves access inside the ChatGPT web app, but API usage is separate. Live recognition still requires a valid `OPENAI_API_KEY` with API billing/credits available on the OpenAI API platform.

New smoke test:

```text
scripts/smoke-ai-vision-quality-profile.ps1
```

The full CI suite now includes Step 47 as a release-blocking AI quality gate, while preserving deterministic fallback for CI environments without a real API key.
