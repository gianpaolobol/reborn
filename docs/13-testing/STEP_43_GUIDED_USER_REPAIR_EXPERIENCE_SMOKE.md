# Step 43 Smoke Test — Guided User Repair Experience

## Script

```text
scripts/smoke-guided-user-repair-experience.ps1
```

## Purpose

This smoke test protects the Step 43 simplification of the first-time user experience.

It verifies that:

- the prototype index loads;
- the primary navigation exposes a small guided repair path;
- advanced/admin labels are no longer present in the primary navigation/footer;
- `app.js` contains the guided repair route, reduced stepper and advanced console directory;
- `reborn.css` contains the new guided repair layout classes;
- a repair user can still log in and create a real repair case through the API.

## Local command

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-guided-user-repair-experience.ps1 -BaseUrl http://127.0.0.1:8080
```

## CI expectation

After Step 43, the full CI suite should report:

```text
Smoke scripts: 34
smoke-guided-user-repair-experience.ps1
```

The release evidence marker is:

```text
STEP43_RELEASE_EVIDENCE_WITH_GUIDED_USER_REPAIR_EXPERIENCE_V1
```
