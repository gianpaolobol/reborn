# STEP 35 — Customer Acceptance, Warranty & Post-Repair Support Governance v1

## Objective

Close the pilot repair loop after proof-of-repair by adding customer acceptance, post-repair support, warranty placeholder governance, customer feedback and human review workflows.

This step keeps the product aligned with the Re-born principle:

> The user does not search for an STL. The user wants the object to work again.

Step 35 therefore adds governance for the moment where the customer confirms whether the object actually works again.

## Added capabilities

- Customer acceptance policies
- Customer acceptance records
- Customer decision workflow
- Warranty policy placeholders
- Warranty cases
- Post-repair support tickets
- Customer feedback records
- Post-repair review queue
- Customer care audit log
- Readiness check `customer_care_governance`
- Prototype console `#/customer-care`
- Smoke test `scripts/smoke-customer-care-warranty-support.ps1`

## New database migration

```text
029_customer_acceptance_warranty_support_governance.sql
```

Tables:

```text
platform_customer_acceptance_policies
platform_customer_acceptance_records
platform_warranty_policies
platform_warranty_cases
platform_post_repair_support_tickets
platform_customer_feedback_records
platform_post_repair_review_items
platform_post_repair_audit_log
```

## New service

```text
src/Platform/Application/CustomerCareGovernanceService.php
```

The service owns the local/pilot customer care workflow:

1. request customer acceptance;
2. record acceptance or issue;
3. create support tickets for issues or questions;
4. create warranty cases for rework/dispute scenarios;
5. record feedback and NPS/satisfaction evidence;
6. create review items for follow-up;
7. keep audit logs.

## API endpoints

```text
GET  /api/v1/platform/customer-care-governance
GET  /api/v1/platform/customer-acceptance-policies
GET  /api/v1/platform/customer-acceptance-records
POST /api/v1/platform/customer-acceptance-records
POST /api/v1/platform/customer-acceptance-records/{id}/decision
GET  /api/v1/platform/warranty-policies
GET  /api/v1/platform/warranty-cases
POST /api/v1/platform/warranty-cases
POST /api/v1/platform/warranty-cases/{id}/status
GET  /api/v1/platform/post-repair-support-tickets
POST /api/v1/platform/post-repair-support-tickets
POST /api/v1/platform/post-repair-support-tickets/{id}/status
GET  /api/v1/platform/customer-feedback-records
POST /api/v1/platform/customer-feedback-records
GET  /api/v1/platform/post-repair-review-items
POST /api/v1/platform/post-repair-review-items/{id}/review
GET  /api/v1/platform/post-repair-audit-log
```

## Prototype route

```text
/prototype/index.html#/customer-care
```

## Smoke test

With the local server running:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-customer-care-warranty-support.ps1
```

## Important scope limitation

Step 35 does **not** implement:

- legally approved warranty terms;
- refunds;
- payment reversal;
- real CRM integration;
- real customer emails/SMS;
- real courier returns;
- statutory consumer-law handling;
- tax/fiscal handling for refunds or compensation.

It is a governance layer for local/pilot evidence. Before a public beta or commercial launch, legal/privacy/consumer-law review is still required.

## Why this step matters

Before Step 35, Re-born could demonstrate repair fulfilment and proof-of-repair.

After Step 35, Re-born can demonstrate whether the customer actually accepted the repair, what happens when the customer reports a problem, how support/warranty follow-up is tracked, and how feedback loops back into platform operations.
