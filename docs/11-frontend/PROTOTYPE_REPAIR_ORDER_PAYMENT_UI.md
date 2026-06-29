# Prototype UI — Repair Order & Payment Intent

Step 14 updates the checkout screen at `#/checkout`.

## User story

After provider matching and quote estimation, the user should be able to turn the quote into a repair order and prepare a mock payment intent.

The screen must communicate that Re-born is confirming a repair journey, not selling a generic model or print.

## Live API behaviour

The UI calls:

- `createRepairOrder(quoteRequestId)`
- `getRepairOrders(caseId)`
- `createPaymentIntent(orderId)`
- `getPaymentIntents(orderId)`
- `confirmMockPaymentIntent(paymentIntentId)`

The Bearer token from Step 10 is used automatically by `api-client.js`.

## Mock fallback behaviour

If the API is not live, the prototype creates local mock objects:

- `mockRepairOrder()`
- `mockPaymentIntent()`

This keeps the journey navigable in static prototype mode.

## UX copy principles

- Avoid “buy STL”.
- Avoid “order print” as the primary framing.
- Use “repair order”, “repair outcome”, “object returns to function”.
- Warn that payment is mock-only in the MVP.

## Visible quality gates

The screen exposes the order gates:

1. quote estimated
2. repair order created
3. payment intent prepared
4. repair outcome pending

These gates prepare the later fulfilment workflow.
