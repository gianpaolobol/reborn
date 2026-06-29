# Provider Trust & Quality QA Checklist

## Smoke test

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-trust-quality.ps1
```

Expected final line:

```text
Provider trust and quality smoke test passed.
```

## Manual API checks

Verify:

- health includes trust capabilities
- repair user can create trust review after completion report
- provider cannot create customer trust review
- duplicate review from the same reviewer does not create duplicate review rows
- provider quality score is updated
- trust signals are listed
- domain events exist

## Manual UI checks

1. Login as repair user.
2. Complete previous repair journey through fulfilment and learning.
3. Open `#/trust`.
4. Click `Record trust review`.
5. Verify quality score metrics appear.
6. Verify trust signal timeline appears.

## Regression checks

Run all smoke tests in order:

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
```
