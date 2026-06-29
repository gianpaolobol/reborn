# Prototype Provider Match & Quote UI

Step 13 updates the prototype so the repair journey can move from decision to fulfilment.

## Main screen

Route:

- `#/provider-network`

The screen now includes:

- Provider Match Engine panel
- Repair context summary
- matched provider cards
- quote request action
- Quote Engine v1 summary panel

## Live API mode

When served through the PHP dev server and the user is authenticated, the prototype calls:

- `POST /api/v1/repair-cases/{id}/provider-matches`
- `GET /api/v1/repair-cases/{id}/provider-matches`
- `POST /api/v1/provider-matches/{id}/quote-requests`
- `GET /api/v1/repair-cases/{id}/quote-requests`

## Mock mode

When the API is unavailable, the prototype still demonstrates:

- mock provider ranking
- mock quote estimate
- repair-first copy and guardrails

## UX principle

Provider matching is intentionally framed as fulfilment of a repair journey. The user is not selecting a print vendor from a marketplace; they are choosing the best path to make the object work again.
