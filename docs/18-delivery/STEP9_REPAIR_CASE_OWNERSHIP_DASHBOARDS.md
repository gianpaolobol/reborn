# Step 9 — Repair Case Ownership & User Dashboards

Step 9 connects the Repair domain to Identity. The platform now understands that a repair case has an owner and that every role needs a different operational surface.

## Added capabilities

- Authenticated repair case creation.
- `owner_id` exposed in the Repair Case domain model and API payloads.
- Repair user list scoped to owned cases.
- Role-aware repair case access policy.
- Dashboard endpoint for the current authenticated user.
- Role-specific dashboard endpoints for repair users, makers, providers, enterprises and admins.
- Admin dashboard previews for other roles.
- Smoke test for ownership and dashboard behavior.

## New / reinforced endpoints

```text
GET  /api/v1/dashboard
GET  /api/v1/dashboards/repair-user
GET  /api/v1/dashboards/maker
GET  /api/v1/dashboards/provider
GET  /api/v1/dashboards/enterprise
GET  /api/v1/dashboards/admin
GET  /api/v1/repair-cases              Bearer required
POST /api/v1/repair-cases              Bearer required
GET  /api/v1/repair-cases/{id}         Bearer required
POST /api/v1/repair-cases/{id}/diagnose Bearer required
```

## Access policy

- `repair_user`: can create and manage own repair cases.
- `enterprise`: can create repair cases and view operational dashboards.
- `maker`: can view diagnosed opportunities and maker dashboard.
- `provider`: can view fulfilment opportunities and provider dashboard.
- `admin`: can access all repair cases, dashboards and operating metrics.

## Development checks

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

In a second PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
```

## Strategic note

This step is important because Re-born is no longer a stateless prototype. It now starts to behave like a SaaS platform where repair requests, makers, providers, enterprise users and operators see different parts of the same Repair Intelligence system.
