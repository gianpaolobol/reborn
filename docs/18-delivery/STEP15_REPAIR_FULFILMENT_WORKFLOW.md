# STEP 15 — Repair Fulfilment Workflow & Provider Acceptance v1

Step 15 turns a mock-authorized repair order into operational repair work. The focus remains the real repair outcome: the object must return to function, not merely receive a file or generic print job.

## Implemented

- New `Fulfilment` bounded slice.
- New `repair_fulfilments` SQLite table.
- Provider acceptance workflow.
- Fulfilment status transitions.
- Fulfilment timeline persisted as JSON.
- Repair order status updated as fulfilment progresses.
- Provider/admin operational permissions.
- Prototype UI route `#/fulfilment`.
- Smoke test `scripts/smoke-repair-fulfilment-workflow.ps1`.

## New API endpoints

- `POST /api/v1/repair-orders/{id}/fulfilments`
- `GET /api/v1/repair-orders/{id}/fulfilments`
- `GET /api/v1/fulfilments/{id}`
- `POST /api/v1/fulfilments/{id}/accept-provider`
- `POST /api/v1/fulfilments/{id}/status`

## Status model

- `awaiting_provider_acceptance`
- `accepted`
- `in_progress`
- `quality_check`
- `ready_to_ship`
- `completed`
- `rejected`

Fulfilment creation requires a `mock_authorized` payment intent. This keeps the MVP ready for real payment adapters while remaining fully local and auditable.

## Domain events

- `repair.fulfilment_requested`
- `repair.fulfilment_provider_accepted`
- `repair.fulfilment_status_updated`

## How to test

Start the local API server:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Run smoke tests from a second PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-path-decision.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-match-quote.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-order-payment-intent.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-fulfilment-workflow.ps1
```

Expected final output:

```text
Repair fulfilment workflow smoke test passed.
```

## MVP limits

- No real payment capture.
- No provider account-to-provider-record mapping yet.
- No shipping carrier integration.
- No maker royalty settlement yet.
- No dispute or refund flow yet.

## Suggested Step 16

`Repair Completion, Learning Event & Knowledge Graph Feedback v1`.

The next step should close the learning loop: completed fulfilments should update repair success signals, provider trust, object-saved metrics, model reliability and Knowledge Graph confidence.
