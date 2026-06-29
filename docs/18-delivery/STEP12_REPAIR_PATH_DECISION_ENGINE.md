# STEP 12 — Repair Path Decision Engine v1

## Goal

Step 12 turns the Step 11 AI recognition output into a repair-first decision. Re-born now ranks concrete repair paths for a real object that must return to function, rather than pushing the user toward a generic STL marketplace.

## What was implemented

Backend additions:

- `repair_path_decisions` SQLite table.
- `RepairPathDecisionEngine` scoring service.
- Persisted decision records.
- Persisted ranked `repair_paths` generated from each decision.
- Protected API endpoints for decision creation, listing and detail.
- Domain events:
  - `repair.path_decision_requested`
  - `repair.path_decision_completed`
- Smoke test:
  - `scripts/smoke-repair-path-decision.ps1`

Prototype additions:

- Step 12 Decision Engine panel after AI recognition.
- Button to generate ranked repair paths from the active recognition job.
- Repair paths screen updated to show latest decision context.
- Mock fallback for static prototype mode.

## Endpoints

### POST `/api/v1/repair-cases/{id}/repair-path-decisions`

Requires Bearer token and mutate access to the repair case.

Request body:

```json
{
  "recognition_job_id": "optional-completed-recognition-job-id"
}
```

If `recognition_job_id` is not provided, the service uses the latest completed recognition job for that repair case. If no recognition job exists, the engine falls back to repair case intake signals.

Response:

```json
{
  "success": true,
  "decision": {
    "id": "...",
    "repair_case_id": "...",
    "recognition_job_id": "...",
    "requested_by": "...",
    "status": "completed",
    "result_json": {
      "decision_factors": {},
      "recommended_path": "generate_part",
      "ranked_paths": [],
      "guardrails": []
    }
  },
  "repair_paths": []
}
```

### GET `/api/v1/repair-cases/{id}/repair-path-decisions`

Requires Bearer token and view access.

Returns all decisions for the repair case, newest first.

### GET `/api/v1/repair-path-decisions/{id}`

Requires Bearer token and view access to the linked repair case.

Returns one decision.

### GET `/api/v1/repair-paths?case_id={id}`

Returns the repair paths persisted by the latest decision. This endpoint existed before Step 12 and remains compatible.

## Ranked repair paths

The Decision Engine v1 currently ranks these path families:

1. `identify_part` — find an existing verified part.
2. `generate_part` — generate an AI repair model as fallback.
3. `ask_maker` — ask a specialist maker to model the component.
4. `find_provider` — route to a local repair provider.
5. `open_bounty` — open a community repair bounty.
6. `enterprise_escalation` — escalate repeatable/batch repairs.

Each path includes:

- score
- title
- description
- estimated price
- estimated days
- next actions
- risk flags

## Guardrails

The engine explicitly preserves Re-born positioning:

- do not sell a file before the repair path is validated;
- AI-generated geometry remains a draft until validated;
- every completed repair should update Repair DNA and the Knowledge Graph.

## How to test

Run setup and start the server:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

In another PowerShell window:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-path-decision.ps1
```

Expected final output:

```text
Repair path decision smoke test passed.
```

## MVP limits

- The scoring model is deterministic and explainable, not a trained ML ranking model.
- The engine uses recognition output and intake text only.
- Provider availability, real inventory and maker capacity are not yet live.
- Risk scoring is a first guardrail model, not safety certification.

## Suggested Step 13

`Provider Match & Quote Engine v1`:

- consume the recommended repair path;
- match provider capabilities to materials and category;
- produce quote candidates;
- create fulfilment-ready provider jobs;
- publish provider match domain events.
