# Step 38 — Continuous Integration Smoke Test Pipeline v1

## Objective

Add a GitHub Actions CI pipeline that validates the Re-born local MVP with PHP 8.4, SQLite and the full smoke test suite.

This step exists because the project cannot depend only on one local Windows machine or on the assistant sandbox. The CI pipeline becomes the repeatable verification layer for future steps.

## Scope

Implemented:

- GitHub Actions workflow for smoke tests;
- PHP 8.4 runtime setup;
- `pdo_sqlite` and `sqlite3` enabled in CI;
- CI `.env` template;
- local SQLite database setup;
- PHP built-in server startup;
- API health wait loop;
- full smoke suite runner;
- failure log artifacts;
- testing documentation;
- pull request checklist update.

## Files added

```text
.github/workflows/smoke-tests.yml
.env.ci.example
scripts/ci-smoke-tests.ps1
docs/13-testing/CI_SMOKE_TEST_PIPELINE.md
docs/09-operations/STEP_38_CONTINUOUS_INTEGRATION_SMOKE_PIPELINE.md
```

## Files modified

```text
.github/PULL_REQUEST_TEMPLATE.md
README.md
```

## Workflow triggers

```text
push to main
pull_request to main
workflow_dispatch
```

## CI runtime

```text
ubuntu-latest
PHP 8.4
pdo
pdo_sqlite
sqlite3
fileinfo
json
PowerShell smoke scripts
SQLite local database
```

## Commands run by CI

```bash
cp .env.ci.example .env
mkdir -p storage/database storage/logs storage/uploads storage/backups
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
pwsh ./scripts/ci-smoke-tests.ps1 -BaseUrl http://127.0.0.1:8080
```

## Design decisions

### Keep rate limiting enabled

CI does not disable rate limiting. It raises the local CI threshold to avoid false negatives during the full smoke suite.

```text
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_REQUESTS=5000
```

This preserves readiness behavior while making regression testing practical.

### Use one central smoke runner

The workflow calls:

```text
scripts/ci-smoke-tests.ps1
```

This prevents the workflow YAML from becoming the canonical list of smoke tests. Future steps must update the script, not duplicate long command lists in multiple places.

### No deployment

This step does not deploy Re-born. It validates the local MVP runtime in CI.

### No secrets

The workflow does not require API keys, payment credentials, provider tokens, AI provider secrets or production databases.

## Out of scope

Not implemented:

- production deployment;
- Docker image publishing;
- MariaDB/MySQL production testing;
- real payment provider testing;
- real AI provider testing;
- browser automation;
- PHPUnit coverage;
- static analysis tools;
- security scanning.

## Acceptance criteria

The step is acceptable when:

- GitHub Actions workflow is present;
- CI uses PHP 8.4;
- CI requests `pdo_sqlite` and `sqlite3`;
- `php scripts/setup-dev.php` runs in CI;
- PHP built-in server starts in CI;
- `/api/health` responds before smoke tests start;
- `scripts/ci-smoke-tests.ps1` runs all current regression smoke scripts;
- failure logs are uploaded without uploading databases or secrets.

## Commit suggestion

```powershell
git status
git add .
git commit -m "ci: add PHP SQLite smoke test pipeline"
git push
```
