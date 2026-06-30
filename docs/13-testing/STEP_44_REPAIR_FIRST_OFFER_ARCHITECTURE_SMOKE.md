# Step 44 Smoke Test — Repair-First Offer Architecture

Smoke script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-first-offer-architecture.ps1 -BaseUrl http://127.0.0.1:8080
```

The test verifies:

- the prototype index exposes the four-step repair-first navigation;
- the user-facing copy focuses on generating a missing replacement part;
- advanced consoles remain grouped away from the primary journey;
- app.js contains the Step 44 offer architecture helpers;
- CSS contains replacement-route card styling;
- the live API health payload exposes Step 44 capabilities;
- a real repair user can still create a repair case through the API.

Expected full-suite result after Step 44:

```text
Smoke scripts: 35
smoke-repair-first-offer-architecture.ps1
Quality gate: passed
```
