# QA Checklist — Repair Completion, Learning & Knowledge Graph Feedback

## Preconditions

- PHP server is running.
- `php scripts/setup-dev.php` has run.
- Step 15 fulfilment workflow works.

## Smoke test

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-completion-learning.ps1
```

## Expected checks

- Health returns ok.
- Repair user can create a repair case.
- Provider match completes.
- Quote is estimated.
- Repair order is created.
- Mock payment is authorized.
- Fulfilment is created.
- Provider accepts fulfilment.
- Fulfilment moves to completed.
- Provider records completion report.
- Learning event is created.
- Knowledge Graph feedback returns a knowledge node id.
- `GET /api/v1/knowledge/nodes` includes a `repair_outcome` node.
- Domain events include:
  - `repair.completion_reported`
  - `learning.event_recorded`
  - `knowledge.graph_feedback_applied`

## Manual prototype check

Open:

```text
http://127.0.0.1:8080/prototype/index.html#/learning
```

Check:

- page renders,
- learning button disabled until fulfilment is completed,
- provider/admin can record learning,
- repair user can view recorded learning after refresh.
