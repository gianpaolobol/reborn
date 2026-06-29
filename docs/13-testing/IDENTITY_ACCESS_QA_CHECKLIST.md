# Identity Access QA Checklist

## Setup

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

## CLI feature test

```powershell
php scripts/run-identity-tests.php
```

## API smoke test

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
```

## Manual checks

- Login succeeds with `admin@reborn.local` / `password`.
- `/api/v1/auth/me` returns current user with bearer token.
- `/api/v1/auth/logout` revokes token.
- Revoked token returns `401 UNAUTHORIZED`.
- Admin token can access `/api/v1/admin/users`.
- Repair user token cannot access `/api/v1/admin/users`.
- Public registration rejects `admin` role.
- Public registration accepts `repair_user`, `maker`, `provider`.
