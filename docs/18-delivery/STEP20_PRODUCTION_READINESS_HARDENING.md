# STEP 20 — Production Readiness Hardening v1

## Goal

Step 20 moves Re-born from a strong end-to-end demo toward an operable system. It does not introduce external infrastructure yet; instead, it adds local, testable production-readiness primitives that can later be replaced or backed by managed services.

The principle remains: Re-born is a Repair Intelligence Platform, not a generic STL marketplace.

## Implemented

- Security headers for API responses and the root app response.
- SQLite-backed fixed-window API rate limiting.
- Public readiness endpoints with safe aggregate checks.
- Admin-only runtime diagnostics.
- Admin-only deploy checklist.
- Admin-only readiness snapshot persistence.
- Backup helper script for the SQLite development database.
- Prototype readiness route at `#/readiness`.
- Production readiness smoke test.

## New configuration

Added `config/security.php` and these `.env.example` keys:

```env
SECURITY_HEADERS_ENABLED=true
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_REQUESTS=240
RATE_LIMIT_WINDOW_SECONDS=60
MAX_UPLOAD_BYTES=15728640
TRUSTED_PROXY_HEADERS=false
```

## New migration

`database/migrations/014_production_readiness_hardening.sql`

Tables:

- `api_rate_limits`
- `platform_readiness_snapshots`
- `platform_audit_log`

## New endpoints

Public:

- `GET /api/ready`
- `GET /api/v1/platform/readiness`
- `GET /api/v1/platform/security-policy`

Admin-only:

- `GET /api/v1/platform/runtime`
- `GET /api/v1/platform/deploy-checklist`
- `POST /api/v1/platform/readiness-snapshots`

## Security headers

API responses now include defensive headers such as:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: no-referrer`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `Cross-Origin-Resource-Policy: same-origin`
- `Cache-Control: no-store, max-age=0`

CSP is intentionally deferred because the current static prototype still uses inline handlers. A strict CSP should be introduced when the prototype is refactored away from inline event handlers.

## Rate limiting

The MVP rate limiter is implemented in `src/Shared/Http/RateLimiter.php` and wired into the router.

It uses:

- token hash when a Bearer token is present;
- IP address when unauthenticated;
- route path;
- fixed window counter.

It is SQLite-backed for local reproducibility. A future production deployment can replace it with Redis or an edge gateway without changing route handlers.

## Prototype UI

The prototype adds:

- `#/readiness`
- navigation item `Readiness`
- readiness check table
- security policy panel
- runtime/admin panel
- deploy checklist
- admin-only snapshot action

## Smoke test

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
```

Expected output:

```text
Production readiness smoke test passed.
```

## Backup helper

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\backup-sqlite.ps1
```

The script copies `storage/database/reborn.sqlite` into `storage/backups/`.

`storage/backups/` is ignored by git.

## Acceptance criteria

Step 20 is complete only when:

- previous smoke tests still pass;
- `/api/ready` returns `ready` or `degraded`;
- security policy endpoint returns enabled headers and rate limiting;
- admin runtime endpoint works with admin bearer token;
- readiness snapshot can be persisted;
- prototype route `#/readiness` loads;
- no local sqlite, logs, uploads or backups are committed.

## Step 21 suggestion

**Observability Dashboard, Backup Automation & Deployment Runbook v1**.

Step 21 should add richer operational visibility, automated backup rotation, restore testing documentation, and a deployment runbook for a real server environment.
