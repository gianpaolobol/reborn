# STEP 21 — Observability Dashboard, Backup Automation & Deployment Runbook v1

## Objective

Make Re-born governable and operable after the Step 20 readiness hardening. Step 21 does not add another repair feature. It gives the operator evidence and controls for running the Repair Journey safely during demos, pilots and future deployments.

## Implemented capabilities

- Admin observability dashboard in the prototype at `#/observability`.
- SQLite-backed HTTP request metrics captured by the router.
- Admin API log viewer for `storage/logs/*.log`.
- Readiness snapshot history.
- SQLite backup creation through API and prototype UI.
- Backup status and restore checklist.
- Deployment runbook endpoint.
- Smoke test run-order summary.
- Step 21 smoke test: `scripts/smoke-observability-ops.ps1`.

## New API endpoints

All endpoints below are admin-only unless noted otherwise.

- `GET /api/v1/platform/observability`
- `GET /api/v1/platform/http-metrics?limit=50`
- `GET /api/v1/platform/logs?limit=80`
- `GET /api/v1/platform/backups`
- `POST /api/v1/platform/backups`
- `GET /api/v1/platform/readiness-snapshots`
- `GET /api/v1/platform/deployment-runbook`
- `GET /api/v1/platform/smoke-tests-summary`

Public readiness remains available at:

- `GET /api/ready`
- `GET /api/v1/platform/readiness`

## New persistence

Migration `015_observability_backup_deployment.sql` adds:

- `platform_http_metrics`
- `platform_backup_runs`
- `platform_deployment_checks`

HTTP metrics are best-effort. If migrations are missing or the database is temporarily unavailable, observability must not break the Repair Journey.

## Local validation sequence

Run from `C:\REBORN\REBORN`.

First terminal:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Second terminal:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-observability-ops.ps1
```

For full regression, run all existing smoke tests, then Step 21 last.

## Operator workflow

1. Login in the prototype as `admin@reborn.local / password`.
2. Open `#/readiness` and persist a readiness snapshot.
3. Open `#/observability`.
4. Confirm HTTP requests are being recorded.
5. Create a SQLite backup.
6. Review logs and deployment runbook before pilot/demo use.

## Restore checklist

1. Stop the PHP server or deployed process.
2. Copy the chosen backup over `storage/database/reborn.sqlite`.
3. Run `php scripts/setup-dev.php`.
4. Restart the server.
5. Run `smoke-production-readiness.ps1` and `smoke-observability-ops.ps1`.

## Deliberate limitations

This is not a replacement for production APM, centralized logging or managed backups. It is a local/pilot operability layer that keeps the MVP honest: observable, restorable and explainable without pretending to be enterprise-grade production.
