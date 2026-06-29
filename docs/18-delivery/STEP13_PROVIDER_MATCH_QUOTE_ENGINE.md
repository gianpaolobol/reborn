# STEP 13 — Provider Match & Quote Engine v1

## Objective

Step 13 connects the ranked repair path from Step 12 to real fulfilment logic. The user is not buying an STL or browsing a generic print marketplace: Re-born now ranks providers by repair fit, trust, capabilities, lead time and validation needs, then creates a preliminary quote estimate for the selected provider.

## Implemented

### Backend

Added a Provider bounded slice for:

- provider matching
- provider match persistence
- quote request persistence
- quote estimation
- provider/quote access control through repair case ownership
- provider and quote domain events

### New database tables

Migration:

- `database/migrations/007_provider_match_quote_engine.sql`

Tables:

- `provider_matches`
- `provider_quote_requests`

### New endpoints

Provider matching:

- `POST /api/v1/repair-cases/{id}/provider-matches`
- `GET /api/v1/repair-cases/{id}/provider-matches`
- `GET /api/v1/provider-matches/{id}`

Quote requests:

- `POST /api/v1/provider-matches/{id}/quote-requests`
- `GET /api/v1/repair-cases/{id}/quote-requests`
- `GET /api/v1/quote-requests/{id}`

### Domain events

- `provider.match_requested`
- `provider.match_completed`
- `quote.requested`
- `quote.estimated`

### Prototype

Updated the prototype provider flow to support:

- Step 13 provider match panel
- matched provider cards
- quote request button
- preliminary quote display
- live API and mock fallback behavior

Main updated files:

- `public/prototype/assets/js/api-client.js`
- `public/prototype/assets/js/state.js`
- `public/prototype/assets/js/app.js`

### Smoke test

Added:

- `scripts/smoke-provider-match-quote.ps1`

The smoke test validates the full chain:

1. health
2. login
3. repair case creation
4. attachment upload
5. AI recognition
6. repair path decision
7. provider match
8. provider match listing/detail
9. quote request
10. quote listing/detail
11. domain events

Expected output:

```text
Provider match and quote smoke test passed.
```

## Acceptance criteria

Step 13 is complete only when all previous smoke tests still pass and the new smoke test passes:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-path-decision.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-match-quote.ps1
```

## MVP limits

- Provider matching is deterministic/mock scoring, not geospatial or payment-aware yet.
- Quote estimates are synchronous and preliminary.
- Providers do not yet accept/reject quotes from their dashboard.
- No payment intent, escrow, wallet event or order fulfilment is created yet.
- Quote expiration is fixed at seven days.

## Next step

Recommended Step 14:

**Repair Order & Payment Intent MVP**

Turn an estimated quote into a repair order draft with order status, platform fee tracking, provider payout placeholder and future Stripe/payment integration point.
