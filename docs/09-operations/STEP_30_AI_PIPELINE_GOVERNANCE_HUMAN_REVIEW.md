# STEP 30 — AI Pipeline Governance & Human-in-the-Loop Review v1

## Goal

Step 30 makes Re-born safer and more governable before real AI providers are integrated.

It does **not** add a real Meshy, Trellis, Rodin or proprietary AI model integration. It creates the operational layer needed to govern those integrations later.

## Product principle

Re-born is not an STL marketplace and it is not an unreviewed AI toy. AI should support the Repair Journey:

> The user's object must return to function.

Every AI output that could affect diagnosis, repair path, printable model, safety, dimensions or dataset reuse must be reviewable and auditable.

## Added capabilities

- AI model provider registry
- AI pipeline run ledger
- Human-in-the-loop review workflow
- AI dataset metadata governance
- AI quality evaluation records
- AI safety rule registry
- AI governance audit log
- Prototype admin console at `#/ai-governance`
- Production readiness check `ai_pipeline_governance`
- Smoke test `scripts/smoke-ai-pipeline-governance.ps1`

## New API endpoints

```text
GET  /api/v1/platform/ai-governance
GET  /api/v1/platform/ai-model-providers
GET  /api/v1/platform/ai-pipeline-runs
POST /api/v1/platform/ai-pipeline-runs
POST /api/v1/platform/ai-pipeline-runs/{id}/review
GET  /api/v1/platform/ai-human-reviews
GET  /api/v1/platform/ai-dataset-items
POST /api/v1/platform/ai-dataset-items
GET  /api/v1/platform/ai-quality-evaluations
POST /api/v1/platform/ai-quality-evaluations/evaluate
GET  /api/v1/platform/ai-safety-rules
GET  /api/v1/platform/ai-governance-audit-log
```

## What remains mock / future scope

- Real image recognition provider integration
- Real image/CAD to 3D generation
- Real STL/CAD hosting and versioning
- Real benchmark datasets
- Automated dimensional validation
- CAD geometry checks
- Safety expert workflows
- External AI vendor credentials and cost tracking
- Model training jobs
- Dataset export for ML training

## Validation

Run with the server already open:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-pipeline-governance.ps1
```

The smoke test logs in as admin, reads the AI governance dashboard, creates a pipeline run, reviews it, creates a dataset item and records a quality evaluation.
