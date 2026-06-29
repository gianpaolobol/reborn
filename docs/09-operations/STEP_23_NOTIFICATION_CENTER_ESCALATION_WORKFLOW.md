# STEP 23 — Notification Center & Escalation Workflow v1

## Goal

Make Re-born operationally actionable after the Step 21 observability layer and Step 22 incident/status layer.

The platform can now create local/mock notification records and escalation runs so operators can prove that alerts and incidents are not only visible, but also routed, tracked and handled.

## Scope

Implemented:

- notification channels stored in SQLite;
- notification rules for alerts, incidents, status updates and maintenance windows;
- notification deliveries with `queued`, `sent`, `failed`, `cancelled` lifecycle;
- escalation policies by severity;
- escalation runs linked to incidents;
- admin API endpoints;
- prototype admin console at `#/notifications`;
- smoke test `scripts/smoke-notification-escalation.ps1`.

Not implemented intentionally:

- real email sending;
- real SMS sending;
- real Slack integration;
- real webhook delivery;
- on-call scheduling;
- external pager integration.

Step 23 keeps transports mock/local because real notification channels require provider selection, secrets management, retries, signatures, privacy review and production monitoring.

## New migration

```text
database/migrations/017_notification_center_escalation_workflow.sql
```

New tables:

```text
platform_notification_channels
platform_notification_rules
platform_notification_deliveries
platform_escalation_policies
platform_escalation_runs
```

## New service

```text
src/Platform/Application/NotificationCenterService.php
```

Responsibilities:

- list and create channels;
- list notification rules;
- dispatch notifications for active operations, alerts, incidents or manual heartbeat;
- mark mock delivery status;
- list escalation policies;
- start escalation runs for active incidents;
- expose dashboard summary.

## Main API endpoints

```text
GET  /api/v1/platform/notification-center
GET  /api/v1/platform/notification-channels
POST /api/v1/platform/notification-channels
GET  /api/v1/platform/notification-rules
GET  /api/v1/platform/notification-deliveries
POST /api/v1/platform/notifications/dispatch
POST /api/v1/platform/notification-deliveries/{id}/status
GET  /api/v1/platform/escalation-policies
GET  /api/v1/platform/escalation-runs
POST /api/v1/platform/incidents/{id}/escalate
```

All mutation and dashboard endpoints are admin-only.

## Prototype UI

```text
/prototype/index.html#/notifications
```

Admin login:

```text
admin@reborn.local / password
```

The console shows:

- channels;
- rules;
- recent deliveries;
- queued deliveries;
- escalation policies;
- active escalation runs;
- buttons to dispatch notifications, create demo channel, mark delivery sent/failed and escalate first incident.

## Smoke test

With PHP server already running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-notification-escalation.ps1
```

The smoke test validates:

1. Step 23 capabilities in `/api/health`.
2. Admin authentication.
3. Notification center dashboard.
4. Channel listing and channel creation.
5. Rule listing.
6. Incident creation.
7. Notification dispatch for that incident.
8. Delivery status update.
9. Escalation policy listing.
10. Incident escalation run creation.
11. Escalation run listing.
12. Incident resolution cleanup.

## Product rationale

This step keeps Re-born aligned with the core product principle:

> The user does not seek an STL. The user wants the object to work again.

A repair platform cannot become reliable merely by matching providers or generating quotes. It must also prove that operational issues are visible, assigned, communicated and escalated. Step 23 therefore strengthens operational trust and platform readiness without adding unrelated marketplace features.
