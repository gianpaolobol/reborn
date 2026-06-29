# CI Auth Guard Marker Debug

This patch adds explicit Step 38 markers to prove GitHub Actions is running the patched scripts:

- `STEP38_WORKFLOW_AUTH_GUARD_V3` in `.github/workflows/smoke-tests.yml`
- `STEP38_CI_AUTH_GUARD_V3` in `scripts/ci-smoke-tests.ps1`
- `STEP38_IDENTITY_SMOKE_GUARD_V3` in `scripts/smoke-identity-access.ps1`

If the GitHub log still reports `Invoke-RestMethod` at line 15 of `smoke-identity-access.ps1`, the workflow is running an old commit or the patched files were not committed/pushed.
