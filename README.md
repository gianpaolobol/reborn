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
