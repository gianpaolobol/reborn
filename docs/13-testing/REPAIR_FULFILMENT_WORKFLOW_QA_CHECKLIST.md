# Repair Fulfilment Workflow QA Checklist

## API

- [ ] Health endpoint returns ok.
- [ ] Repair user can create repair case.
- [ ] Repair user can create provider match.
- [ ] Repair user can request quote.
- [ ] Repair user can create repair order.
- [ ] Repair user can create payment intent.
- [ ] Repair user can mock-authorize payment intent.
- [ ] Repair user can create fulfilment.
- [ ] Repair user can list fulfilments.
- [ ] Repair user can view fulfilment detail.
- [ ] Provider can accept fulfilment.
- [ ] Provider can update fulfilment status.
- [ ] Fulfilment timeline grows after each transition.
- [ ] Domain events are persisted.

## Frontend

- [ ] Checkout shows Start fulfilment after mock authorization.
- [ ] Fulfilment route renders without console errors.
- [ ] Repair user sees timeline.
- [ ] Provider/admin sees operational action buttons.
- [ ] Mock fallback works without API.

## Smoke test

- [ ] `smoke-repair-fulfilment-workflow.ps1` passes.
