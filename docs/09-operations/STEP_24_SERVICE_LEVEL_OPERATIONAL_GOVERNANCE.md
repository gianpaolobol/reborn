# STEP 24 — Service Level & Operational Governance v1

## Goal

Step 24 turns Re-born's operating layer into measurable commitments.

After Step 21 observability, Step 22 incidents/status and Step 23 notifications/escalations, the platform can now express and track:

- response and resolution targets for alerts and incidents;
- SLA evaluations stored in SQLite;
- manual response/resolution evidence;
- operational policies for pilot readiness, incident communication, backup/restore, provider governance and upload-data handling;
- policy attestations by an admin/operator;
- a prototype console at `#/service-governance`.

This remains a local/pilot governance layer. It is not a legal SLA contract and does not replace privacy/legal/terms work.

## New database migration

```text
018_service_level_operational_governance.sql
```

New tables:

```text
platform_sla_policies
platform_sla_evaluations
platform_operational_policies
platform_policy_attestations
```

Seeded records include alert/incident SLA targets and operational policies for pilot readiness, incident communications, backup/restore, provider quality and upload-data handling.

## New application service

```text
src/Platform/Application/OperationalGovernanceService.php
```

Responsibilities:

- list SLA policies;
- evaluate active alerts/incidents against SLA policies;
- persist or update SLA evaluations;
- mark SLA first response;
- mark SLA resolution;
- list operational policies;
- record policy attestations;
- provide the admin governance dashboard payload.

## API endpoints

Admin-only endpoints:

```text
GET  /api/v1/platform/service-governance
GET  /api/v1/platform/sla-policies
POST /api/v1/platform/slas/evaluate
GET  /api/v1/platform/sla-evaluations
POST /api/v1/platform/sla-evaluations/{id}/response
POST /api/v1/platform/sla-evaluations/{id}/resolve
GET  /api/v1/platform/operational-policies
GET  /api/v1/platform/policy-attestations
POST /api/v1/platform/operational-policies/{id}/attest
```

## Prototype UI

Open:

```text
http://127.0.0.1:8080/prototype/index.html#/service-governance
```

Login:

```text
admin@reborn.local
password
```

The UI allows an admin to:

- evaluate SLAs;
- create a demo SLA incident;
- mark SLA response/resolution;
- view SLA policies;
- view operational policies;
- attest policies.

## Smoke test

With the PHP server running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-service-governance-sla.ps1
```

Recommended Step 24 verification sequence:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-observability-ops.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-incident-response-status.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-notification-escalation.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-service-governance-sla.ps1
```

## Product note

Step 24 is intentionally operational. It does not add another marketplace feature. It strengthens the spine needed for controlled beta and future enterprise trust:

- reliable evidence of readiness;
- visible incident handling;
- explicit operational commitments;
- policy governance before real users/providers are onboarded.
