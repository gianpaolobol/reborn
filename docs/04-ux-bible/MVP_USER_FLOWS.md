# MVP User Flows

## Flow 1 — Repair user creates a case

```text
Homepage
  -> Start repair
  -> Register/Login if needed
  -> Upload photos
  -> Describe issue
  -> Add optional brand/model/dimensions
  -> Review
  -> Submit
  -> Case timeline: waiting for classification
```

### UX rules

- Keep language non-technical.
- Ask one thing per step.
- Explain why each input matters.
- Show photo guidance before upload.
- Allow draft saving.

---

## Flow 2 — Admin classifies early case

```text
Admin dashboard
  -> Case queue
  -> Open submitted case
  -> Inspect photos and description
  -> Assign category/product/component/damage
  -> Set confidence
  -> Choose missing data or continue
  -> Generate diagnosis summary
```

### UX rules

- Admin decisions must be auditable.
- Corrections must create learning signals.
- Low-confidence classification must remain visible.

---

## Flow 3 — User chooses repair path

```text
Case timeline
  -> Diagnosis summary
  -> Repair path comparison
  -> Choose path:
      A. provider quote
      B. maker bounty
      C. existing model
      D. expert review
      E. AI generation waitlist/placeholder
```

### UX rules

- Use plain comparison: cost, speed, confidence, effort.
- Do not hide unavailable routes.
- Explain when a route is experimental.

---

## Flow 4 — Provider quote

```text
Provider dashboard
  -> Incoming request
  -> Request detail
  -> Inspect Repair DNA
  -> Send quote
  -> User receives quote
  -> User accepts/rejects placeholder
```

### UX rules

- Provider sees structured data, not raw chaos.
- Provider can ask for additional info.
- Quote must include material, price, time and assumptions.

---

## Flow 5 — Maker bounty

```text
User selects Maker Bounty
  -> Bounty created from Repair DNA
  -> Maker dashboard shows bounty
  -> Maker reviews details
  -> Maker submits candidate solution
  -> Admin reviews model
  -> User sees model candidate
```

### UX rules

- Bounty must be framed as “find or create a repair solution”.
- Reward/credit system can be placeholder in MVP.
- Model approval protects trust.

---

## Flow 6 — Outcome feedback

```text
Case timeline
  -> Mark result
  -> Success / Failure / Abandoned
  -> Upload final photo optional
  -> Explain result
  -> Impact summary
  -> Knowledge signal stored
```

### UX rules

- Failure is valuable data.
- Never make the user feel blamed.
- Show sustainability impact only when credible.
