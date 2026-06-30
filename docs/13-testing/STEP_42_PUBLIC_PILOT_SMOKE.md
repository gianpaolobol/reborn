# Step 42 Smoke Test — Public Pilot Demo, Partner Intake & Real-World Validation

Script:

```text
scripts/smoke-public-pilot-real-world-validation.ps1
```

## What it verifies

1. `/api/health` exposes Step 42 public pilot capabilities.
2. `/api/ready` includes the `public_pilot` readiness check.
3. Public demo endpoint is reachable without authentication.
4. Public intake endpoint accepts a controlled external lead.
5. Admin can list public pilot dashboard data.
6. Admin can review/shortlist an intake submission.
7. Admin can create a real-world validation case from intake.
8. Admin can create and approve validation cases.
9. Admin can list lead scores.
10. Admin can evaluate public pilot readiness.
11. Public pilot audit log records actions.

## Expected result

```text
Step 42 public pilot demo, external intake and real-world validation smoke test passed.
```

## Non-goals

The smoke test does not validate production hosting, real email delivery, real provider onboarding, payments, payouts, logistics, warranty handling or external sustainability claims.
