# STEP 39 — Regression Test Matrix, Release Evidence & Quality Gate v1

## Objective

Transform the validated Step 38 GitHub Actions smoke suite into a release governance layer.

Step 38 proved that the full smoke suite can run in CI with PHP 8.4, `pdo_sqlite` and SQLite. Step 39 makes that result useful for release decisions by generating:

- a regression test matrix;
- release evidence;
- a quality gate report;
- CI artifacts that can be attached to PRs, demos and internal board reporting.

## Why this matters

Re-born now has many bounded contexts and governance surfaces. A green smoke suite is useful, but it does not explain what was covered.

The Step 39 matrix maps every smoke script to a product capability, a step, a domain, a strategic asset and a gate level. This helps future agents avoid adding features without regression coverage.

## Implemented

Added:

```text
scripts/ci-release-evidence.ps1
docs/13-testing/REGRESSION_TEST_MATRIX.md
docs/13-testing/RELEASE_EVIDENCE_AND_QUALITY_GATE.md
docs/09-operations/STEP_39_REGRESSION_TEST_MATRIX_RELEASE_EVIDENCE_QUALITY_GATE.md
```

Modified:

```text
.github/workflows/smoke-tests.yml
.github/PULL_REQUEST_TEMPLATE.md
README.md
docs/13-testing/CI_SMOKE_TEST_PIPELINE.md
```

## CI behaviour

After the full smoke suite, GitHub Actions now runs:

```powershell
./scripts/ci-release-evidence.ps1 -BaseUrl http://127.0.0.1:8080 -AllowFailedSuite
```

It writes:

```text
storage/logs/ci-regression-test-matrix.json
storage/logs/ci-release-evidence.json
storage/logs/ci-quality-gate.json
storage/logs/ci-release-evidence.md
```

The workflow uploads those files as:

```text
reborn-ci-release-evidence
```

## Quality gate

The quality gate passes only if:

- the smoke summary exists;
- the smoke suite status is `passed`;
- all matrix scripts exist;
- every matrix row has a corresponding result;
- no smoke result is missing from the matrix;
- all release-blocking rows passed;
- health, readiness and status endpoints are reachable.

## Current matrix size

The Step 39 matrix contains 30 rows, covering Step 8 through Step 37.

## Important limitation

This is not production certification. It is CI-level release governance for a local/pilot/beta platform.

It does not certify:

- legal readiness;
- GDPR compliance;
- payment compliance;
- cybersecurity penetration testing;
- ESG reporting;
- real provider fulfilment;
- real AI provider performance.

## Future step rule

Every future step that adds a smoke test must update:

```text
scripts/ci-smoke-tests.ps1
scripts/ci-release-evidence.ps1
docs/13-testing/REGRESSION_TEST_MATRIX.md
```

A pull request that changes API behaviour without updating the matrix should not be merged.
