# Acceptance Criteria

## Repair case intake

A repair case is accepted when:

- the user can create it without technical terms;
- at least one image or description is provided;
- the system stores status, timestamps and user reference;
- the case can be resumed later;
- missing data can be requested.

---

## Classification

Classification is accepted when:

- product category can be assigned;
- component can be assigned or marked unknown;
- confidence can be stored;
- admin can correct classification;
- corrections are logged as learning signals.

---

## Repair path recommendation

A recommendation is accepted when:

- it has a path type;
- it explains why it is suggested;
- it shows uncertainty or missing data;
- it offers at least one next action;
- it can be rejected or changed.

---

## Provider request

A provider request is accepted when:

- user can request production from a repair case;
- provider receives relevant data;
- provider can send quote;
- user can accept or reject quote;
- status is tracked.

---

## Maker model

A model is accepted when:

- it has title, category, component, file reference and license/monetization metadata;
- it can be linked to repair cases;
- it can collect outcome feedback;
- it has verification state.

---

## Outcome feedback

Outcome feedback is accepted when:

- user can mark success or failure;
- feedback can be tied to repair case, model and provider;
- feedback changes trust signals;
- notes and photos can be attached.
