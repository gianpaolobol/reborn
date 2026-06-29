# MVP Route Map

## Public

| Method | Route | Purpose |
|---|---|---|
| GET | / | Homepage |
| GET | /how-it-works | Explanation |
| GET | /for-makers | Maker landing |
| GET | /for-providers | Provider landing |
| GET | /login | Login form |
| POST | /login | Create session |
| GET | /register | Register form |
| POST | /register | Create user |
| POST | /logout | Destroy session |

## Repair user

| Method | Route | Purpose |
|---|---|---|
| GET | /app | User dashboard |
| GET | /app/repairs/new | Start repair form |
| POST | /app/repairs | Create repair case |
| GET | /app/repairs/{id}/photos | Upload photos page |
| POST | /app/repairs/{id}/photos | Upload photo |
| GET | /app/repairs/{id}/details | Details form |
| PATCH | /app/repairs/{id} | Update case details |
| GET | /app/repairs/{id}/review | Review page |
| POST | /app/repairs/{id}/submit | Submit case |
| GET | /app/repairs/{id} | Timeline |
| GET | /app/repairs/{id}/diagnosis | Diagnosis |
| GET | /app/repairs/{id}/paths | Path comparison |
| POST | /app/repairs/{id}/paths/select | Select path |
| POST | /app/repairs/{id}/quote-requests | Request quote |
| POST | /app/repairs/{id}/bounties | Create bounty |
| GET | /app/repairs/{id}/outcome | Outcome form |
| POST | /app/repairs/{id}/outcome | Store outcome |

## Provider

| Method | Route | Purpose |
|---|---|---|
| GET | /provider | Provider dashboard |
| GET | /provider/onboarding | Provider setup |
| POST | /provider/profile | Create/update profile |
| GET | /provider/requests | Incoming requests |
| GET | /provider/requests/{id} | Request detail |
| POST | /provider/quotes | Send quote |

## Maker

| Method | Route | Purpose |
|---|---|---|
| GET | /maker | Maker dashboard |
| GET | /maker/onboarding | Maker setup |
| POST | /maker/profile | Create/update profile |
| GET | /maker/models/new | Submit model page |
| POST | /maker/models | Submit model metadata |
| GET | /maker/bounties | Bounty list |
| GET | /maker/bounties/{id} | Bounty detail |
| POST | /maker/bounties/{id}/submissions | Submit solution metadata |

## Admin

| Method | Route | Purpose |
|---|---|---|
| GET | /admin | Admin dashboard |
| GET | /admin/repair-cases | Case queue |
| GET | /admin/repair-cases/{id} | Case detail |
| PATCH | /admin/classifications/{id} | Update classification |
| POST | /admin/repair-cases/{id}/missing-data | Request missing data |
| POST | /admin/repair-cases/{id}/safety-flags | Add safety flag |
| GET | /admin/models | Model review queue |
| PATCH | /admin/models/{id}/review | Review model |
| GET | /admin/providers | Provider review queue |
| PATCH | /admin/providers/{id}/review | Review provider |
| GET | /admin/makers | Maker review queue |
| PATCH | /admin/makers/{id}/review | Review maker |
| GET | /admin/taxonomy | Taxonomy manager |
| GET | /admin/knowledge-signals | Learning signals |
| GET | /admin/metrics | Metrics dashboard |
