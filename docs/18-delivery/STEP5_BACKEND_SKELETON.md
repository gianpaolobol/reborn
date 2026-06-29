# Step 5 — Backend Skeleton

## Purpose

Step 5 turns Re-born from a static prototype into a backend-ready MVP foundation.

This is not yet production software. It is a clean, modular PHP skeleton designed to prove the architectural direction before adding authentication, uploads, payment, AI integrations and provider workflows.

## What was added

- PHP 8.3 project skeleton
- No framework
- Custom PSR-4 autoload fallback
- Front controller in `public/index.php`
- JSON API router
- SQLite development database
- Migration runner
- Seed data
- Clean Architecture folder structure
- DDD-aligned bounded contexts
- Repair Case API
- Mock Recognition Engine
- Mock Knowledge Engine
- Repair Path Decision Service
- Provider Matching Service
- Domain Event persistence

## First commands

```powershell
cd C:\REBORN\REBORN
php scripts/doctor.php
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Open:

```text
http://127.0.0.1:8080
http://127.0.0.1:8080/api/health
http://127.0.0.1:8080/prototype/index.html
```

## MVP API endpoints

```text
GET  /api/health
GET  /api/v1/repair-cases
POST /api/v1/repair-cases
GET  /api/v1/repair-cases/{id}
POST /api/v1/repair-cases/{id}/diagnose
GET  /api/v1/repair-paths?case_id={id}
GET  /api/v1/providers
GET  /api/v1/knowledge/nodes
```

## Architectural rule

All future implementation must preserve the separation between:

```text
Domain
Application
Infrastructure
Presentation
```

Controllers must stay thin. Business decisions belong in application services and domain objects.
