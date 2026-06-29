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
