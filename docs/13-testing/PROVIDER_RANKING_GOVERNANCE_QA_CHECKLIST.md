# QA Checklist — Provider Ranking & Governance

## Smoke test

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-ranking-governance.ps1
```

## Manual API checks

1. Login as admin.
2. Check `/api/v1/governance/policies`.
3. Create a ranking snapshot.
4. Record a watchlist action on a provider.
5. Create a second ranking snapshot.
6. Confirm the provider has `routing_status = watchlist`.
7. Confirm governance summary counts active actions.
8. Confirm domain events exist.

## Prototype checks

1. Open `/prototype/index.html#/login`.
2. Login as `admin@reborn.local` / `password`.
3. Go to `#/governance`.
4. Click `Create ranking snapshot`.
5. Click `Watchlist top provider`.
6. Confirm provider ranking and action timeline update.
7. Refresh API and confirm state persists.

## Acceptance criteria

- Admin-only mutations are enforced.
- Provider rankings are explainable.
- Governance actions affect ranking output.
- Domain events are generated.
- Audit table is populated.
- Prototype works in live and mock mode.
