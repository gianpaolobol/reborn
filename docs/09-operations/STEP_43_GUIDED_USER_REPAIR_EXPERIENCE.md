# Step 43 — Guided User Repair Experience Simplification v1

## Purpose

Step 43 corrects the main usability problem discovered after Step 42: the prototype had become operationally rich but confusing for a first-time repair user.

The new rule is simple:

> A repair user should see one linear path. Operators and investors can still access governance consoles, but those consoles must not dominate the primary navigation.

## What changed

Step 43 introduces a simplified user-facing repair journey:

1. **Guide** — explain the repair-first promise and show the current next action.
2. **Describe** — capture the broken object and problem in plain language.
3. **Evidence** — add photos, dimensions or optional files.
4. **Options** — rank repair paths after diagnosis.
5. **Quote** — route to providers and request a quote.
6. **Confirm** — create order/payment intent/fulfilment in the existing mock-governed flow.

## UX changes

- The default prototype route now opens the guided repair experience.
- The top navigation is reduced to the user journey plus login/account.
- Advanced governance, pilot and investor consoles are grouped under `#/advanced`.
- The old 31-step progress indicator is replaced by a six-step repair progress indicator.
- `#/repair-guide` gives a first-time user a single next action based on the current repair state.
- `#/overview` keeps the platform-level overview for investors/operators.

## What did not change

Step 43 does **not** remove backend governance modules. It only changes how they are exposed in the prototype.

The following remain available through `#/advanced` or direct links:

- readiness;
- observability;
- incidents;
- notifications;
- privacy;
- release management;
- partner onboarding;
- revenue;
- maker economy;
- AI governance;
- geometry/printability;
- provider routing;
- dispatch;
- customer care;
- sustainability;
- investor reporting;
- demo walkthrough;
- pilot launch;
- public pilot.

## Product rule for future steps

New modules may be added only if they do one of two things:

1. improve the guided repair journey for normal users; or
2. remain grouped in the advanced/operator area without adding more primary navigation clutter.

The product remains a Repair Intelligence Platform, not a catalogue of files or dashboards.
