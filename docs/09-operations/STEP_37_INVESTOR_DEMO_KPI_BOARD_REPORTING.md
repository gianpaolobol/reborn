# Step 37 — Investor Demo, KPI Narrative & Board Reporting Governance v1

## Goal

Make Re-born easier to present and govern as an investor/demo system without pretending it is already production-grade. Step 37 turns existing operational layers into a structured boardroom narrative: KPI definitions, KPI snapshots, demo story sections, board reports, evidence records and demo-readiness reviews.

## Product principle

Re-born remains a Repair Intelligence Platform. Investor reporting must prove the repair journey: intake, AI governance, maker economy, provider routing, dispatch, customer acceptance, sustainability impact and operational readiness. It must not reduce the product to STL downloads or isolated AI generation.

## Added components

- `platform_investor_kpi_definitions`
- `platform_investor_kpi_snapshots`
- `platform_demo_narrative_sections`
- `platform_board_reports`
- `platform_board_report_sections`
- `platform_board_report_evidence`
- `platform_investor_demo_readiness_reviews`
- `platform_investor_reporting_audit_log`
- `InvestorReportingService`
- Admin prototype route `#/investor-reporting`
- Smoke test `scripts/smoke-investor-reporting-board-readiness.ps1`

## What it can demonstrate

- Local KPI definitions by category and narrative role.
- A KPI snapshot aggregating repair, provider, maker, AI, geometry, dispatch, customer-care and impact evidence.
- Demo narrative sections for problem, solution, moat, business model and readiness.
- Board report generation from KPI snapshot and narrative sections.
- Board evidence records that explicitly identify pilot/local confidence.
- Demo-readiness reviews with blockers and next steps.

## Explicit non-goals

Step 37 does **not** create:

- audited financials;
- legal investment documents;
- certified traction numbers;
- public ESG or LCA reporting;
- investor CRM workflows;
- automated pitch decks;
- real ARR, GMV or payout accounting.

All metrics are local/pilot evidence and must remain caveated until validated with real beta usage, partner agreements, legal review and production integrations.

## Smoke test

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-investor-reporting-board-readiness.ps1
```

## Demo route

```text
http://127.0.0.1:8080/prototype/index.html#/investor-reporting
```

## Commit suggestion

```powershell
git status
git add .
git commit -m "platform: add investor demo KPI and board reporting governance v1"
git push
```
