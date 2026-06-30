# Step 41 Smoke Test — Demo Data Room, Pilot Launch Pack & Stakeholder Feedback Loop

Script:

```text
scripts/smoke-demo-data-room-pilot-feedback-loop.ps1
```

## What it validates

The smoke test verifies:

- `/api/health` exposes Step 41 capabilities;
- `/api/ready` includes the `pilot_launch` readiness check;
- admin login works;
- the pilot launch dashboard is readable;
- data room assets are listed and a new asset can be created;
- pilot checklist items are listed and can be marked ready;
- stakeholder feedback loops can be created;
- stakeholder feedback can be recorded and listed;
- post-demo reports can be created and listed;
- pilot go/no-go decisions can be generated and listed;
- pilot launch audit log records activity.

## Local command

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-demo-data-room-pilot-feedback-loop.ps1 -BaseUrl http://127.0.0.1:8080
```

## CI integration

Step 41 updates:

```text
scripts/ci-smoke-tests.ps1
scripts/ci-release-evidence.ps1
.github/workflows/smoke-tests.yml
docs/13-testing/REGRESSION_TEST_MATRIX.md
```

The Step 41 release evidence marker is:

```text
STEP41_RELEASE_EVIDENCE_WITH_PILOT_LAUNCH_V1
```

The Step 41 smoke suite marker is:

```text
STEP41_CI_SMOKE_SUITE_WITH_PILOT_LAUNCH_V1
```
