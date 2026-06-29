# CI Smoke Test Pipeline

## Purpose

Step 38 introduces the GitHub Actions smoke test pipeline for Re-born.

The goal is to make every future step verifiable outside the local Windows machine by running the same PHP/SQLite stack expected by the MVP:

- PHP 8.4;
- PDO;
- `pdo_sqlite`;
- `sqlite3`;
- local SQLite database;
- PHP built-in server;
- PowerShell smoke scripts.

This is a CI smoke suite, not a production deployment pipeline.

## Workflow

GitHub Actions workflow:

```text
.github/workflows/smoke-tests.yml
```

It runs on:

- push to `main`;
- pull request targeting `main`;
- manual `workflow_dispatch`.

## Runtime

The workflow uses:

```text
ubuntu-24.04
PHP 8.4
shivammathur/setup-php@v2
actions/checkout@v5
actions/upload-artifact@v6
```

Required PHP extensions:

```text
pdo
pdo_sqlite
sqlite3
fileinfo
json
```

The workflow copies `.env.ci.example` to `.env` before running setup.

After `php scripts/setup-dev.php`, CI runs:

```text
scripts/verify-demo-credentials.php
```

This verifies that all demo accounts exist, are active, have the expected roles and that the shared demo password `password` is accepted by `password_verify()`. This catches broken seed hashes before the API smoke tests start.

The CI environment intentionally sets a high local rate limit:

```text
RATE_LIMIT_MAX_REQUESTS=5000
```

This prevents the full smoke suite from being rate-limited while keeping rate limiting enabled for readiness checks.

## Local equivalent

From the repository root:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

In a second PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\ci-smoke-tests.ps1 -BaseUrl http://127.0.0.1:8080
```

## Smoke suite order

The central runner is:

```text
scripts/ci-smoke-tests.ps1
```

It runs the current regression suite in chronological dependency order:

1. Identity;
2. ownership dashboards;
3. prototype auth UI;
4. repair upload and recognition;
5. repair path decision;
6. provider quote;
7. repair order and payment intent;
8. fulfilment;
9. learning;
10. trust;
11. governance;
12. admin ops;
13. production readiness;
14. observability;
15. incident response;
16. notifications;
17. SLA governance;
18. privacy governance;
19. release management;
20. partner onboarding;
21. marketplace revenue;
22. maker economy;
23. AI pipeline governance;
24. AI provider sandbox;
25. geometry printability;
26. provider routing;
27. dispatch and proof-of-repair;
28. customer care;
29. sustainability impact;
30. investor reporting.

## Legacy/manual smoke scripts

The CI runner intentionally does not execute these older manual smoke helpers:

```text
smoke-api.ps1
smoke-backend-hardening.ps1
smoke-prototype-api.ps1
```

They pre-date the authenticated Repair Journey flow and are kept as manual/debug helpers. The canonical regression list is `scripts/ci-smoke-tests.ps1`.

## Future step rule

Every future platform step that adds an API surface and a smoke test must also update:

```text
scripts/ci-smoke-tests.ps1
```

If a new step adds CI-relevant environment variables, update:

```text
.env.ci.example
```

If a smoke test should not run in CI, document why in the step handoff and in this file.

## Failure artifacts

On failure, the workflow writes a GitHub error annotation naming the failed smoke script, writes a step summary, and uploads runtime diagnostics from:

```text
storage/logs/*.log
storage/logs/*.json
```

Do not upload `.env`, SQLite databases, uploads, backups or other runtime data that may contain tokens or user data.

## Auth preflight hardening

The CI pipeline resets demo credentials after `scripts/setup-dev.php` using `scripts/reset-demo-credentials.php`.
This avoids false failures when demo users already exist with stale hashes or when a seed file was changed after the local database was created.

Before the full smoke suite starts, `scripts/ci-api-auth-preflight.ps1` verifies:

- `/api/health` is reachable.
- `admin@reborn.local` can log in with password `password` through the HTTP API.
- `/api/v1/auth/me` accepts the returned bearer token.

If this preflight fails, the workflow writes:

- `storage/logs/ci-auth-preflight.json`
- `storage/logs/ci-auth-preflight-failure.json`
- `storage/logs/ci-php-server.log`

These artifacts identify whether the failure is a database seed issue, an API auth issue, or a server runtime issue.

## Demo credential guard

The CI smoke suite performs a demo credential reset and verification immediately before executing the API smoke scripts. This is deliberately duplicated inside `scripts/ci-smoke-tests.ps1`, not only in the workflow YAML, so that manual invocations of the suite also get the same deterministic auth setup.

`DEMO_AUTH_FALLBACK_ENABLED=true` is set only in `.env.ci.example`. Production environments must not enable this flag.
