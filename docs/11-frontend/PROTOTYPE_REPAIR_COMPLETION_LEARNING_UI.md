# Prototype UI — Repair Completion Learning

Step 16 adds the `#/learning` prototype route.

## User experience

The page explains the final repair intelligence loop:

1. Provider completes fulfilment.
2. Provider/admin records completion outcome.
3. Re-born creates a completion report.
4. Re-born records a learning event.
5. Re-born applies Knowledge Graph feedback.

## Important UX principle

The UI must keep saying that the end goal is a working object, not delivery of a file or model.

## Live mode

In Live API mode, the button calls:

```text
POST /api/v1/fulfilments/{id}/completion-reports
```

Then refreshes:

```text
GET /api/v1/fulfilments/{id}/completion-reports
GET /api/v1/repair-cases/{id}/learning-events
```

## Mock mode

In static/mock mode, the page creates local mock completion reports and learning events so the prototype remains navigable without PHP.
