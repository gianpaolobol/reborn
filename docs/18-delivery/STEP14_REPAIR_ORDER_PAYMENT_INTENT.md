# STEP 14 — Repair Order & Payment Intent MVP

## Goal

Step 14 converts a preliminary provider quote into a persistent repair order and prepares a mock payment intent. The goal is to model checkout as a repair fulfilment commitment, not as a generic product purchase.

The MVP deliberately does not connect Stripe, PayPal or any external payment provider. `payment_intents` are mock records that make the order lifecycle auditable and ready for a future adapter.

## Implemented backend slice

New migration:

- `database/migrations/008_repair_order_payment_intent.sql`

New tables:

- `repair_orders`
- `payment_intents`

New application/domain/infrastructure/presentation classes under `src/Marketplace`:

- `RepairOrder`
- `PaymentIntent`
- `RepairOrderRepository`
- `PaymentIntentRepository`
- `RepairOrderAssembler`
- `CreateRepairOrderService`
- `ListRepairOrdersService`
- `GetRepairOrderService`
- `CreatePaymentIntentService`
- `ListPaymentIntentsService`
- `GetPaymentIntentService`
- `ConfirmMockPaymentIntentService`
- `SqliteRepairOrderRepository`
- `SqlitePaymentIntentRepository`
- `RepairOrderController`

## API endpoints

```http
POST /api/v1/quote-requests/{id}/repair-orders
GET  /api/v1/repair-cases/{id}/repair-orders
GET  /api/v1/repair-orders/{id}
POST /api/v1/repair-orders/{id}/payment-intents
GET  /api/v1/repair-orders/{id}/payment-intents
GET  /api/v1/payment-intents/{id}
POST /api/v1/payment-intents/{id}/confirm-mock
```

All endpoints require Bearer authentication and reuse `RepairCaseAccessPolicy`.

## Domain events

Step 14 adds:

- `repair.order_created`
- `payment.intent_created`
- `payment.intent_mock_authorized`

## Repair order model

A repair order is created only from an estimated quote request. It persists:

- quote request id
- provider match id
- repair case id
- provider id
- ordering user
- order status
- subtotal
- platform fee
- provider payout
- total
- quality gates
- repair success definition

The repair success definition is explicit: the order is successful when the object returns to function, not when a file or printed part is merely delivered.

## Payment intent model

The payment intent is mock-only in Step 14.

Initial status:

- `requires_mock_confirmation`

After test confirmation:

- `mock_authorized`

No real charge is made. The table stores a mock `client_secret`, a local prototype payment URL and metadata needed for a future real payment adapter.

## Prototype UI

The checkout route `#/checkout` now shows:

- active quote summary
- repair order creation action
- payment intent creation action
- mock payment authorization action
- platform fee
- provider payout
- order quality gates
- no-real-money warning

## Smoke test

New script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-order-payment-intent.ps1
```

Expected final output:

```text
Repair order and payment intent smoke test passed.
```

## Acceptance criteria

Step 14 is complete when:

- all previous smoke tests pass
- `smoke-provider-match-quote.ps1` passes
- `smoke-repair-order-payment-intent.ps1` passes
- repair orders are persisted
- payment intents are persisted
- mock confirmation updates payment intent status
- domain events are emitted
- prototype checkout can create order/payment intent in live API mode

## MVP limits

- No Stripe, PayPal or bank integration.
- No real money movement.
- No refunds or chargebacks.
- No provider acceptance workflow yet.
- No order fulfilment status machine beyond creation and mock payment authorization.

## Suggested Step 15

**Repair Fulfilment Workflow & Provider Acceptance v1**

Recommended next features:

- provider accepts or rejects an order
- order status machine
- production checklist
- customer/provider messaging placeholder
- completion confirmation
- repair outcome feedback
- trust update event
- impact accounting event
