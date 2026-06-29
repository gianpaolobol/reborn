# MVP Information Architecture

## Public

- `/` — Homepage
- `/how-it-works` — How it works
- `/for-makers` — Maker acquisition
- `/for-providers` — Provider acquisition
- `/login` — Login
- `/register` — Register

## Repair user app

- `/app` — User dashboard
- `/app/repairs/new` — Start repair
- `/app/repairs/{id}/photos` — Photo upload
- `/app/repairs/{id}/details` — Guided description
- `/app/repairs/{id}/review` — Review and submit
- `/app/repairs/{id}` — Case timeline
- `/app/repairs/{id}/diagnosis` — Diagnosis summary
- `/app/repairs/{id}/paths` — Repair path comparison
- `/app/repairs/{id}/quotes` — Quote requests
- `/app/repairs/{id}/outcome` — Outcome confirmation

## Maker app

- `/maker` — Maker dashboard
- `/maker/onboarding` — Maker profile
- `/maker/models/new` — Submit model metadata
- `/maker/bounties` — Open bounties
- `/maker/bounties/{id}` — Bounty detail

## Provider app

- `/provider` — Provider dashboard
- `/provider/onboarding` — Provider profile and capabilities
- `/provider/requests` — Incoming requests
- `/provider/requests/{id}` — Request detail
- `/provider/quotes/new?request={id}` — Quote creation

## Admin app

- `/admin` — Admin dashboard
- `/admin/repair-cases` — Case queue
- `/admin/repair-cases/{id}` — Case review
- `/admin/classifications/{id}` — Classification editor
- `/admin/models` — Model queue
- `/admin/providers` — Provider queue
- `/admin/makers` — Maker queue
- `/admin/taxonomy` — Product/component taxonomy
- `/admin/knowledge-signals` — Learning signals
- `/admin/metrics` — MVP metrics

## Navigation principles

1. The primary object is always the repair case.
2. Marketplace functions appear only as possible repair paths.
3. The user is never asked to “search STL” as a primary action.
4. Every screen must show the next useful action.
5. Low confidence must be honest and visible.
