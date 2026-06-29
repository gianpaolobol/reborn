# QA Checklist — Repair Order & Payment Intent MVP

## Backend

- [ ] `php scripts/setup-dev.php` runs migration `008_repair_order_payment_intent.sql`.
- [ ] `POST /api/v1/quote-requests/{id}/repair-orders` requires auth.
- [ ] Repair user can create order for own repair case quote.
- [ ] Unrelated user cannot create order for another user quote.
- [ ] `GET /api/v1/repair-cases/{id}/repair-orders` lists persisted orders.
- [ ] `GET /api/v1/repair-orders/{id}` returns order details.
- [ ] `POST /api/v1/repair-orders/{id}/payment-intents` creates a mock intent.
- [ ] `GET /api/v1/repair-orders/{id}/payment-intents` lists intents.
- [ ] `GET /api/v1/payment-intents/{id}` returns intent details.
- [ ] `POST /api/v1/payment-intents/{id}/confirm-mock` changes status to `mock_authorized`.

## Domain events

- [ ] `repair.order_created` is emitted.
- [ ] `payment.intent_created` is emitted.
- [ ] `payment.intent_mock_authorized` is emitted.

## Prototype

- [ ] Login works.
- [ ] Provider quote can be created.
- [ ] Checkout shows quote total, platform fee and provider payout.
- [ ] “Create repair order” creates/persists the order.
- [ ] “Create payment intent” creates/persists the mock intent.
- [ ] “Mock authorize” updates the payment intent.
- [ ] UI clearly says no real money is moved.

## Smoke command

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-order-payment-intent.ps1
```

Expected output:

```text
Repair order and payment intent smoke test passed.
```
