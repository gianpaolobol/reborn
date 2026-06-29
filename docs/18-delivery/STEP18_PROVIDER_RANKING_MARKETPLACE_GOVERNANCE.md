# STEP 18 — Provider Ranking Feedback & Marketplace Governance v1

## Goal

Step 18 moves Re-born from an end-to-end demo flow toward an operable marketplace system. Provider trust scores now become governed routing decisions rather than passive reputation data.

The principle remains repair-first: the platform is not ranking STL sellers. It is deciding which providers should safely receive real repair demand.

## Implemented scope

### New bounded context

- `src/Governance/`

### New database migration

- `database/migrations/012_provider_ranking_marketplace_governance.sql`

### New tables

- `provider_governance_actions`
- `provider_ranking_snapshots`
- `marketplace_governance_audit`

### New endpoints

- `POST /api/v1/governance/ranking-snapshots`
- `GET /api/v1/governance/ranking-snapshots/latest`
- `GET /api/v1/governance/provider-rankings`
- `POST /api/v1/providers/{id}/governance-actions`
- `GET /api/v1/providers/{id}/governance-actions`
- `GET /api/v1/governance/actions`
- `GET /api/v1/governance/summary`
- `GET /api/v1/governance/policies`

### Domain events

- `governance.provider_action_recorded`
- `governance.provider_ranking_snapshot_created`

## Ranking formula v1

The ranking engine combines:

- provider quality score;
- reliability score;
- communication score;
- timeliness score;
- seed provider rating where no repair outcome exists yet;
- active governance adjustment.

Routing statuses:

- `eligible`
- `watchlist`
- `suppressed`

Admin governance actions can change routing by adding watchlist, suppress, manual boost, manual penalty, quality review or policy note actions.

## Why this matters

Before real users and real provider demand are routed through Re-born, the platform needs governance primitives:

- explainable rankings;
- manual operational intervention;
- audit trail;
- admin-only ranking publication;
- watchlist and suppression logic;
- clear marketplace policy.

This step makes the provider network more controllable and prepares the future admin console.

## Testing

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-ranking-governance.ps1
```

Expected final output:

```text
Provider ranking and marketplace governance smoke test passed.
```

## MVP limits

- Ranking is snapshot-based, not async.
- Governance policy is code-defined, not editable in UI yet.
- Actions can be created but not resolved through an endpoint yet.
- Provider ranking is still based on seeded provider data until enough real repair outcomes exist.

## Step 19 suggestion

**Admin Operations Console & Moderation Workflow v1**: expose governance actions, queues, flags, provider review, case interventions and audit history in a true ops dashboard.
