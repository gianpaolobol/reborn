# Provider Match & Quote QA Checklist

## Backend

- [ ] `php scripts/setup-dev.php` runs migrations including `007_provider_match_quote_engine.sql`.
- [ ] `GET /api/health` includes `provider_match_engine` and `provider_quote_engine`.
- [ ] Authenticated repair user can create a provider match for an owned repair case.
- [ ] Provider match result contains `repair_context` and `ranked_providers`.
- [ ] Provider match list endpoint returns persisted matches.
- [ ] Provider match detail endpoint enforces repair case access.
- [ ] Quote request endpoint rejects missing `provider_id`.
- [ ] Quote request endpoint rejects providers not present in the match result.
- [ ] Valid quote request returns status `estimated`.
- [ ] Quote JSON includes `total_cents`, `platform_fee_cents`, `provider_payout_cents`, `line_items`, `assumptions` and `estimated_days`.
- [ ] Quote list and detail endpoints return persisted quote requests.

## Domain events

- [ ] `provider.match_requested`
- [ ] `provider.match_completed`
- [ ] `quote.requested`
- [ ] `quote.estimated`

## Prototype

- [ ] `#/provider-network` loads without JS errors.
- [ ] Match providers button works after a repair case exists.
- [ ] Provider cards show match-based pricing after provider match.
- [ ] Request quote button creates a quote in live mode.
- [ ] Quote panel shows estimated total, provider and expiration.
- [ ] Mock fallback still works when API is not live.

## Smoke test

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-match-quote.ps1
```

Expected:

```text
Provider match and quote smoke test passed.
```
