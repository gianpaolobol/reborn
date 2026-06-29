# Prototype UI — Marketplace Governance

Step 18 adds a new prototype route:

- `#/governance`

The top navigation now includes `Governance`.

## UI capabilities

The governance screen shows:

- active governance actions;
- ranked provider count;
- eligible / watchlist counts;
- latest ranking snapshot metadata;
- provider ranking table;
- governance action timeline;
- policy summary.

Admin users can:

- create a provider ranking snapshot;
- record a watchlist governance action for the top provider;
- refresh governance data.

## Live API mode

When authenticated as admin, the prototype calls:

- `POST /api/v1/governance/ranking-snapshots`
- `POST /api/v1/providers/{id}/governance-actions`
- `GET /api/v1/governance/summary`
- `GET /api/v1/governance/actions`
- `GET /api/v1/governance/provider-rankings`

## Mock fallback mode

When the API is unavailable, the prototype creates mock governance snapshots and actions locally so the UX remains navigable.

## UX principle

Governance is presented as a repair-safety layer, not as a generic marketplace ranking. A provider is ranked by ability to help an object return to function safely and reliably.
