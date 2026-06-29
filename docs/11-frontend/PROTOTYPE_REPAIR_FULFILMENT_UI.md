# Prototype Repair Fulfilment UI

Step 15 adds a fulfilment layer to the prototype.

## Route

- `#/fulfilment`

## UI behaviour

The checkout page now exposes a `Start fulfilment` action after a payment intent has been mock-authorized. The fulfilment page shows:

- repair order id
- payment status
- provider id
- fulfilment status
- provider acceptance status
- operational timeline
- provider actions

Provider/admin users can:

- accept the fulfilment
- move fulfilment to `in_progress`
- move fulfilment to `quality_check`
- complete the fulfilment

Repair users can view the state and timeline.

## UX principle

The fulfilment page explicitly frames success as a functional repair outcome. It avoids treating the order as completed because a file or print job was created.
