# Production Readiness QA Checklist

## Setup

```powershell
cd C:\REBORN\REBORN
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Second PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
```

## Manual checks

- `/api/health` returns `production_readiness_hardening` in capabilities.
- `/api/ready` returns `ready` or `degraded`.
- `/api/v1/platform/security-policy` returns `security_headers_enabled = true`.
- Response headers include `X-Content-Type-Options` and `X-Frame-Options`.
- Login as `admin@reborn.local / password`.
- `/api/v1/platform/runtime` works only for admin.
- `/api/v1/platform/deploy-checklist` works only for admin.
- `POST /api/v1/platform/readiness-snapshots` persists a snapshot.
- Prototype `#/readiness` loads and refreshes.
- `scripts/backup-sqlite.ps1` creates a backup under `storage/backups/`.

## Regression smoke suite

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-path-decision.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-match-quote.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-order-payment-intent.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-fulfilment-workflow.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-completion-learning.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-trust-quality.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-ranking-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-admin-ops-moderation.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
```

## Git hygiene

Do not commit:

- `.env`
- `storage/database/*.sqlite`
- `storage/logs/*`
- `storage/uploads/*`
- `storage/backups/*`
