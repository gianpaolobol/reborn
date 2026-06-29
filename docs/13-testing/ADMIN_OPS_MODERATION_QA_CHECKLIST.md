# Admin Operations & Moderation QA Checklist

## Smoke test

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-admin-ops-moderation.ps1
```

Expected final output:

```text
Admin operations and moderation smoke test passed.
```

## Manual checks

1. Start PHP server.
2. Open `http://127.0.0.1:8080/prototype/index.html#/login`.
3. Login as `admin@reborn.local` / `password`.
4. Open `#/ops`.
5. Create review item.
6. Assign item.
7. Record action.
8. Create escalation.
9. Resolve item.
10. Refresh API data.
11. Verify queue and summary update.

## Regression checks

Run the full suite:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-path-decision.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-match-quote.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-order-payment-intent.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-fulfilment-workflow.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-completion-learning.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-trust-quality.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-ranking-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-admin-ops-moderation.ps1
```
