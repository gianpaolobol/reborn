# STEP 26 — Beta Release Management & Pilot Readiness v1

## Purpose

Step 26 gives Re-born a controlled release-management layer before any real beta or pilot. The goal is not to add a new marketplace feature, but to govern whether the current Repair Journey MVP can be shown to pilots, what is enabled, what remains disabled, and what evidence blocks or allows a release.

## What was added

- Feature flags for local/pilot rollout controls.
- Release records for beta/demo milestones.
- Release gate evaluation based on current platform evidence.
- Pilot cohorts for repair users, maker/providers and enterprise design partners.
- Pilot participant records with consent and onboarding state.
- Admin prototype console: `#/release-management`.
- Smoke test: `scripts/smoke-beta-release-management.ps1`.

## New database migration

```text
database/migrations/020_beta_release_management_pilot_readiness.sql
```

New tables:

```text
platform_feature_flags
platform_releases
platform_release_gates
platform_release_decisions
platform_pilot_cohorts
platform_pilot_participants
```

## New service

```text
src/Platform/Application/ReleaseManagementService.php
```

The service provides:

- `dashboard()`
- `betaReadiness()`
- `featureFlags()`
- `updateFeatureFlag()`
- `releases()`
- `createRelease()`
- `evaluateReleaseGates()`
- `releaseGates()`
- `decideRelease()`
- `releaseDecisions()`
- `pilotCohorts()`
- `updatePilotCohort()`
- `pilotParticipants()`
- `addPilotParticipant()`
- `updatePilotParticipant()`

## New API endpoints

```text
GET  /api/v1/platform/release-management
GET  /api/v1/platform/beta-readiness
GET  /api/v1/platform/feature-flags
POST /api/v1/platform/feature-flags/{id}
GET  /api/v1/platform/releases
POST /api/v1/platform/releases
POST /api/v1/platform/releases/{id}/evaluate-gates
GET  /api/v1/platform/releases/{id}/gates
POST /api/v1/platform/releases/{id}/decision
GET  /api/v1/platform/release-decisions
GET  /api/v1/platform/pilot-cohorts
POST /api/v1/platform/pilot-cohorts/{id}
GET  /api/v1/platform/pilot-participants
POST /api/v1/platform/pilot-participants
POST /api/v1/platform/pilot-participants/{id}
```

## Release gates

Step 26 evaluates these gates:

- production readiness is acceptable;
- recent backup exists;
- no open critical incidents;
- no active SLA breach blocking the pilot;
- privacy notices, processing records and retention rules exist;
- data subject requests are under control;
- risky features stay disabled;
- pilot cohorts are defined;
- feature flags are configured;
- operational policies are active.

The gate model deliberately distinguishes:

- `passed`
- `warning`
- `failed`

A warning can still allow a local demo if manually accepted. A failed required gate blocks beta readiness.

## Feature-flag intent

Safe/local features are enabled or beta by default:

```text
live_repair_intake
ai_recognition_mock
mock_payments
provider_onboarding
public_status_page
dsr_json_export
```

Risky or not-yet-production features remain disabled:

```text
real_ai_recognition
ai_3d_generation
real_payments
maker_economy
```

These must stay disabled until legal, privacy, security, cost-control and support workflows are ready.

## Smoke test

With the PHP server running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-beta-release-management.ps1
```

The smoke test verifies:

- health capabilities;
- readiness includes the Step 26 check;
- admin login;
- backup and readiness snapshot evidence;
- release management dashboard;
- beta readiness gates;
- feature flag listing and update;
- release gate evaluation;
- release decision recording;
- pilot cohort update;
- pilot participant creation and activation.

## Product note

This step supports the product principle that Re-born is not an STL marketplace. It governs the Repair Journey as a system: what is safe to expose, who can participate, and which operational evidence proves that a demo or beta is credible.
