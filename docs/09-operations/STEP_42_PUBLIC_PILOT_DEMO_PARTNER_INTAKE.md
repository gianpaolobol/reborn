# Step 42 — Public Pilot Demo, Partner Intake & Real-World Validation Pack v1

## Objective

Step 42 turns the Step 41 data room and pilot launch pack into a controlled external pilot entry point. The goal is not to launch Re-born publicly. The goal is to invite the right early stakeholders into a governed validation loop.

## Added capabilities

- Public pilot demo payload at `/api/v1/public-pilot-demo`.
- Public no-auth pilot intake endpoint at `/api/v1/public-pilot-intake`.
- Admin dashboard at `/api/v1/platform/public-pilot`.
- Prototype console at `#/public-pilot`.
- Partner/provider/maker/repair-user intake submissions.
- Stakeholder lead scoring.
- Real-world validation cases.
- Public pilot evaluation and audit log.

## Governance caveat

A public pilot intake submission is not acceptance into the platform. It does not enable real payments, payouts, provider activation, fulfilment, warranty obligations or certified sustainability claims. Every external lead must still pass privacy/legal/provider/fulfilment/customer-care readiness gates.

## API surface

```text
GET  /api/v1/public-pilot-demo
POST /api/v1/public-pilot-intake
GET  /api/v1/platform/public-pilot
GET  /api/v1/platform/public-pilot-pages
GET  /api/v1/platform/pilot-intake-submissions
POST /api/v1/platform/pilot-intake-submissions/{id}/review
POST /api/v1/platform/pilot-intake-submissions/{id}/validation-case
GET  /api/v1/platform/real-world-validation-cases
POST /api/v1/platform/real-world-validation-cases
POST /api/v1/platform/real-world-validation-cases/{id}/status
GET  /api/v1/platform/pilot-stakeholder-lead-scores
POST /api/v1/platform/public-pilot/evaluate
GET  /api/v1/platform/public-pilot-audit-log
```

## Smoke test

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-public-pilot-real-world-validation.ps1
```

## CI expectation

The full CI smoke suite must report 33 scripts. The release evidence matrix must include Step 42 as a release-blocking real-world validation gate.
