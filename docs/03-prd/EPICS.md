# MVP Epics

## EPIC-01 — Identity and Role Access

Enable users to create accounts and operate as repair users, makers, providers or admins.

### Success signal

A user can register, log in and access the correct dashboard based on role.

### Main stories

- user registration;
- user login;
- role selection;
- profile basics;
- admin role management.

---

## EPIC-02 — Repair Case Creation

Allow a repair user to create a structured case from photos, text and guided metadata.

### Success signal

A repair case can be created and persisted with photos, description and Repair DNA draft.

### Main stories

- start repair;
- upload photos;
- add issue description;
- add brand/model/dimensions;
- save draft;
- submit case.

---

## EPIC-03 — Repair DNA and Classification

Transform raw user input into structured intelligence.

### Success signal

Every submitted case has a product category, component candidate, damage type and confidence value.

### Main stories

- classify category;
- classify component;
- flag missing data;
- admin correction;
- store learning signal.

---

## EPIC-04 — Repair Path Routing

Show the user the most useful next action.

### Success signal

The user can choose between at least two meaningful routes: provider quote, maker bounty, existing model, expert/manual review or AI placeholder.

### Main stories

- path comparison screen;
- provider quote request;
- maker bounty creation;
- existing model suggestion;
- AI generation waiting-list/placeholder.

---

## EPIC-05 — Maker Model Contribution

Allow makers to enrich the repair ecosystem by contributing model metadata.

### Success signal

A maker can publish a model candidate linked to product/component taxonomy.

### Main stories

- maker onboarding;
- model upload metadata;
- compatibility information;
- version notes;
- admin review;
- bounty response.

---

## EPIC-06 — Provider Quote Flow

Allow distributed print/service providers to receive qualified repair requests and quote them.

### Success signal

A provider can receive a request, understand the part, quote it and update the quote status.

### Main stories

- provider onboarding;
- capability setup;
- request inbox;
- quote creation;
- quote status;
- provider feedback.

---

## EPIC-07 — Admin Intelligence Console

Create a control layer for validating, correcting and learning from early cases.

### Success signal

An admin can review every important object in the MVP: cases, classifications, models, providers, makers and outcomes.

### Main stories

- case review;
- taxonomy edit;
- classification correction;
- model approval;
- provider approval;
- outcome review;
- learning signal inspection.

---

## EPIC-08 — Knowledge Graph Foundation

Persist relationships between users, objects, components, models, providers, materials and repair outcomes.

### Success signal

Each repair creates or updates graph-ready records.

### Main stories

- entity persistence;
- relationship persistence;
- outcome feedback;
- reusable classification signals;
- admin graph inspection.

---

## EPIC-09 — Trust, Safety and Liability Baseline

Prevent unsafe, illegal or misleading repair flows.

### Success signal

Risky cases can be flagged, blocked or routed to expert/manual review.

### Main stories

- unsafe category rules;
- restricted item detection/manual flag;
- disclaimer screens;
- provider/maker verification status;
- report model/case.

---

## EPIC-10 — Metrics and Operating Dashboard

Track whether the MVP is learning and repairing.

### Success signal

The team can see the core funnel and Knowledge Graph growth.

### Main stories

- case funnel metrics;
- solution route metrics;
- repair success metrics;
- provider response metrics;
- model contribution metrics;
- objects saved estimate.
