# STEP 31 — AI Provider Adapter Sandbox & Job Orchestration v1

## Goal

Prepare Re-born for real AI integrations without enabling unsafe or ungoverned external calls.

Step 31 sits after Step 30. Step 30 governs AI outputs, human review, datasets and quality. Step 31 governs the operational layer that will eventually call providers such as Meshy, Trellis, Rodin or internal diagnosis workers.

The principle remains:

> Re-born does not generate files for their own sake. AI must support the Repair Journey and help an object work again.

## Implemented

- AI provider adapter registry for sandbox providers.
- Mock adapter health checks.
- AI orchestration job queue.
- Job lifecycle: queued, running, succeeded, failed, retry scheduled and cancelled.
- Job event timeline.
- Artifact stubs for future AI outputs.
- Provider cost ledger for reserved/spent mock costs.
- Sandbox audit log.
- Admin prototype console at `#/ai-provider-sandbox`.
- Readiness check `ai_provider_sandbox`.
- Smoke test `scripts/smoke-ai-provider-sandbox-orchestration.ps1`.

## New database migration

```text
025_ai_provider_adapter_sandbox_job_orchestration.sql
```

New tables:

```text
platform_ai_provider_adapters
platform_ai_orchestration_jobs
platform_ai_job_events
platform_ai_artifact_stubs
platform_ai_provider_cost_ledger
platform_ai_provider_sandbox_audit_log
```

## New service

```text
src/Platform/Application/AiProviderSandboxService.php
```

Responsibilities:

- list adapters;
- evaluate adapter health;
- create sandbox jobs;
- advance jobs through a mock lifecycle;
- retry or cancel jobs;
- create artifact placeholders;
- write event, cost and audit records.

## New API endpoints

```text
GET  /api/v1/platform/ai-provider-sandbox
GET  /api/v1/platform/ai-provider-adapters
POST /api/v1/platform/ai-provider-adapters/health-check
GET  /api/v1/platform/ai-orchestration-jobs
POST /api/v1/platform/ai-orchestration-jobs
POST /api/v1/platform/ai-orchestration-jobs/{id}/advance
POST /api/v1/platform/ai-orchestration-jobs/{id}/retry
POST /api/v1/platform/ai-orchestration-jobs/{id}/cancel
GET  /api/v1/platform/ai-job-events
GET  /api/v1/platform/ai-artifact-stubs
GET  /api/v1/platform/ai-provider-cost-ledger
GET  /api/v1/platform/ai-provider-sandbox-audit-log
```

## Prototype

Open:

```text
http://127.0.0.1:8080/prototype/index.html#/ai-provider-sandbox
```

Login as admin:

```text
admin@reborn.local
password
```

The console exposes:

- adapter health;
- queued/running jobs;
- job event timeline;
- artifact stubs;
- cost ledger;
- sandbox audit records.

## Smoke test

With the PHP server running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-provider-sandbox-orchestration.ps1
```

Suggested regression order from Step 31 onward:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-observability-ops.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-incident-response-status.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-notification-escalation.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-service-governance-sla.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-privacy-data-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-beta-release-management.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-partner-onboarding-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-marketplace-revenue-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-maker-economy-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-pipeline-governance.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-provider-sandbox-orchestration.ps1
```

## Explicitly out of scope

Step 31 does **not** implement:

- real Meshy API calls;
- real Trellis worker execution;
- real Rodin/Tripo calls;
- real STL/CAD generation;
- GPU worker deployment;
- real provider billing;
- background daemons;
- webhooks from AI providers;
- production AI cost controls.

It creates the governance and orchestration layer that must exist before those integrations are made real.

## Next natural step

A later step can add a controlled worker abstraction or provider-specific adapter interface. That should only happen after Step 31 smoke tests pass and after secrets, budgets, human review gates and release flags are explicitly controlled.
