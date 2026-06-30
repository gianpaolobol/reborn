# Release Evidence & Quality Gate

## Purpose

Step 39 adds a release evidence layer on top of the validated GitHub Actions smoke suite.

The smoke suite proves that the API flows still work. The release evidence layer proves what was tested, which product areas were covered, which release gates passed, and which runtime produced the result.

## Script

The evidence generator is:

```text
scripts/ci-release-evidence.ps1
```

It runs in CI after the smoke suite and can also be run locally after `scripts/ci-smoke-tests.ps1`.

Local command:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\ci-release-evidence.ps1 -BaseUrl http://127.0.0.1:8080
```

## Generated artifacts

The script writes:

```text
storage/logs/ci-regression-test-matrix.json
storage/logs/ci-release-evidence.json
storage/logs/ci-quality-gate.json
storage/logs/ci-release-evidence.md
```

GitHub Actions uploads them as:

```text
reborn-ci-release-evidence
```

## Quality gate checks

The Step 39 quality gate verifies:

- `ci-smoke-results.json` exists;
- the full smoke suite status is `passed`;
- all matrix scripts exist;
- all matrix scripts have a smoke result;
- there are no smoke results missing from the matrix;
- all `release-blocking` rows passed;
- `/api/health` is reachable;
- `/api/ready` is reachable;
- `/api/status` is reachable.

## What this does not certify

This is not a legal, security, financial, ESG or production certification.

It is a CI-level release governance control for local/pilot/beta readiness. It proves that the current commit can run the local Repair Journey and operational governance suite in a reproducible PHP 8.4 + SQLite environment.

## Merge rule

A branch is not ready to merge into `main` unless:

- the smoke suite passes;
- the Step 39 quality gate passes;
- the release evidence artifact is uploaded;
- any skipped or intentionally failing area is documented in the pull request.
