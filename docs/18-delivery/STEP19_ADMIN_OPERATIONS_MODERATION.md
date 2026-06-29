# STEP 19 — Admin Operations Console & Moderation Workflow v1

## Objective

Step 19 turns the Re-born end-to-end repair journey into a governable operating system. The goal is not to add another demo screen, but to give admins and ops users a structured way to review risk, moderate questionable items, escalate decisions and keep an audit trail before routing real repair demand.

## What was implemented

- New bounded context: `src/Operations/`
- New SQLite migration: `database/migrations/013_admin_operations_moderation_workflow.sql`
- New tables:
  - `ops_review_items`
  - `ops_moderation_actions`
  - `ops_escalations`
  - `ops_audit_log`
- New admin-only API endpoints for review queues, moderation, escalation and summary.
- New prototype route: `#/ops`
- New smoke test: `scripts/smoke-admin-ops-moderation.ps1`

## Why it matters

A repair marketplace cannot scale safely on matching and checkout alone. It needs operational governance:

- unsafe repair cases must be reviewed;
- provider quality concerns must be triaged;
- content/model/quote disputes must be moderated;
- high-risk cases need escalation;
- every admin mutation must be auditable.

## New API endpoints

Admin-only endpoints:

- `POST /api/v1/ops/review-items`
- `GET /api/v1/ops/review-items`
- `GET /api/v1/ops/review-items/{id}`
- `POST /api/v1/ops/review-items/{id}/assign`
- `POST /api/v1/ops/review-items/{id}/moderation-actions`
- `POST /api/v1/ops/review-items/{id}/escalations`
- `POST /api/v1/ops/review-items/{id}/resolve`
- `GET /api/v1/ops/escalations`
- `GET /api/v1/ops/summary`
- `GET /api/v1/ops/policies`

## Domain events

Step 19 publishes:

- `ops.review_item_created`
- `ops.review_item_assigned`
- `ops.moderation_action_recorded`
- `ops.escalation_created`
- `ops.review_item_resolved`

## Policy v1

`AdminOperationsPolicy` defines:

- review item statuses;
- priority SLA;
- review categories;
- moderation action types;
- escalation levels;
- admin-only mutation rule.

## Acceptance criteria

Step 19 is complete when:

- all previous smoke tests still pass;
- `scripts/smoke-admin-ops-moderation.ps1` passes;
- admin can create a review item;
- admin can assign it;
- admin can record a moderation action;
- admin can escalate it;
- admin can resolve it;
- summary counts are updated;
- domain events are persisted;
- prototype `#/ops` renders correctly.

## Next step

Step 20 should focus on **Production Readiness Hardening v1**: permissions, rate limits, security headers, environment checks, deployment checklist, error budgets and release gates.
