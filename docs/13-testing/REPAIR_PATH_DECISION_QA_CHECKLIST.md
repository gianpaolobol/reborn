# Repair Path Decision QA Checklist

## Required smoke test

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-path-decision.ps1
```

Expected output:

```text
Repair path decision smoke test passed.
```

## What the smoke test validates

- Health endpoint returns ok.
- Repair user can log in.
- Repair user can create a repair case.
- Repair user can upload a diagnostic PNG.
- AI recognition job completes.
- Repair Path Decision Engine completes.
- Decision contains `recommended_path`.
- Decision contains multiple `ranked_paths`.
- Ranked paths are persisted in `repair_paths`.
- Decision can be listed by repair case.
- Decision detail can be read.
- Domain events are generated:
  - `repair.path_decision_requested`
  - `repair.path_decision_completed`

## Manual QA

1. Start the PHP server.
2. Open `http://127.0.0.1:8080/prototype/index.html#/login`.
3. Login as `repair.user@reborn.local` / `password`.
4. Create a repair case from `#/start`.
5. Upload at least one image from `#/capture`.
6. Run AI recognition.
7. Generate repair paths.
8. Verify that `#/repair-paths` shows ranked cards and the latest decision context.

## Regression checks

Also run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
```

## Failure notes

If PowerShell reports an invalid variable reference near a colon, wrap the variable name with `${...}`. Example:

```powershell
Ok "Domain event ${expected}: ok"
```
