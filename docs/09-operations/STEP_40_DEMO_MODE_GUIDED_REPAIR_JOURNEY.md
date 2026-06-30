# Step 40 — Demo Mode, Guided Repair Journey & Investor Walkthrough v1

Step 40 turns the verified CI/release governance foundation into a guided demo layer. It helps operators present Re-born as a repair intelligence platform rather than a file marketplace.

## Scope

Implemented local/pilot governance for:

- demo modes for investor and beta/operator walkthroughs;
- guided repair journey steps;
- demo sessions and step events;
- demo feedback capture;
- demo readiness reviews;
- demo walkthrough audit log;
- prototype console at `#/demo-walkthrough`;
- smoke test coverage in the full CI suite.

## Caveats

This step does not create production claims. The guided demo must still explain that:

- AI providers remain governed/sandboxed until real integrations are approved;
- payments, payouts and refunds remain mock/local governance;
- shipment and warranty flows are not legal/production commitments;
- sustainability values are pilot estimates, not certified claims;
- investor KPIs are local evidence, not audited financials.

## API surface

- `GET /api/v1/platform/demo-walkthrough`
- `GET /api/v1/platform/demo-modes`
- `GET /api/v1/platform/demo-walkthrough-steps`
- `GET /api/v1/platform/demo-sessions`
- `POST /api/v1/platform/demo-sessions`
- `POST /api/v1/platform/demo-sessions/{id}/advance`
- `GET /api/v1/platform/demo-session-events`
- `GET /api/v1/platform/demo-feedback`
- `POST /api/v1/platform/demo-feedback`
- `GET /api/v1/platform/demo-readiness-reviews`
- `POST /api/v1/platform/demo-readiness/evaluate`
- `POST /api/v1/platform/demo-readiness-reviews/{id}/review`
- `GET /api/v1/platform/demo-walkthrough-audit-log`

## Smoke test

Run after the Step 39 quality gate and full CI smoke suite:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-demo-walkthrough-investor-journey.ps1
```

The CI suite now includes this smoke test as the 31st release gate.
