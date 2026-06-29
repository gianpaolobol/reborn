# Repair Journey Flow

## Entry points

- Homepage CTA: Start a repair.
- Upload photo CTA.
- Search by object/component.
- Provider/maker shared link.
- Enterprise portal case creation.

---

## Main user flow

```text
Start Repair
  -> Upload Photos
  -> Describe Problem
  -> Confirm Object / Component
  -> Add Missing Measurements
  -> Review Repair Diagnosis
  -> Choose Repair Path
       -> Existing Model
       -> Provider Print
       -> Maker Bounty
       -> Spare Part
       -> AI Model Generation
       -> Manual Review
  -> Obtain Solution
  -> Execute Repair
  -> Confirm Outcome
  -> Knowledge Graph Update
```

---

## Key UX checkpoints

### Checkpoint 1 — Intake complete

The user has provided enough information to create a repair case.

### Checkpoint 2 — Diagnosis usable

The system has enough confidence to suggest at least one path or ask a precise question.

### Checkpoint 3 — Path selected

The user has chosen what to do next.

### Checkpoint 4 — Solution delivered

A model, part, print or provider action has been delivered.

### Checkpoint 5 — Outcome captured

The user confirms repair success or failure.

---

## Failure flows

- Object cannot be identified.
- Component cannot be identified.
- Photos are insufficient.
- Part is safety-critical.
- No model exists.
- No provider available nearby.
- Provider rejects request.
- Model fails fit.
- User abandons case.

Each failure flow must still create useful knowledge.
