# STEP 17 — Trust, Reputation & Provider Quality Scoring v1

## Objective

Step 17 adds the first trust and reputation layer to Re-born. Provider reputation is not a generic marketplace star rating: it is a structured signal derived from completed repairs, customer confirmation, object-saved outcomes, quality, communication and timeliness.

The goal is to make future provider matching stronger by feeding real repair outcomes back into provider quality scoring.

## Implemented scope

- New Trust bounded context under `src/Trust`.
- Trust review creation after a completion report exists.
- Provider trust signal persistence.
- Provider quality score recalculation.
- Trust tier assignment.
- Prototype UI route `#/trust`.
- API client support for trust endpoints.
- Smoke test for the full Step 17 flow.

## Data model

### `provider_trust_reviews`

Stores the customer or enterprise review connected to a real completion report.

Core fields:

- `completion_report_id`
- `fulfilment_id`
- `repair_case_id`
- `provider_id`
- `reviewer_id`
- `reviewer_role`
- `rating_overall`
- `rating_quality`
- `rating_communication`
- `rating_timeliness`
- `would_recommend`
- `issue_resolved`
- `signals_json`

A unique index prevents the same user from reviewing the same completion report twice.

### `provider_trust_signals`

Stores structured trust events derived from reviews.

Core fields:

- `provider_id`
- `repair_case_id`
- `completion_report_id`
- `trust_review_id`
- `event_type`
- `signal_json`
- `score_delta`

### `provider_quality_scores`

Stores the current calculated provider quality score.

Core fields:

- `review_count`
- `completed_repairs_count`
- `successful_repairs_count`
- `average_rating`
- `quality_score`
- `reliability_score`
- `communication_score`
- `timeliness_score`
- `overall_score`
- `trust_tier`
- `score_json`

## Trust formula v1

The MVP score uses weighted sub-scores:

- Quality: 35%
- Reliability: 35%
- Communication: 15%
- Timeliness: 15%

Reliability includes successful repair outcome, object saved, recommendation and issue resolved signals.

Trust tiers:

- `unrated`
- `watchlist`
- `emerging`
- `qualified`
- `trusted`
- `elite`

`elite` requires both a high score and at least three reviews.

## API endpoints

### Create trust review

`POST /api/v1/completion-reports/{id}/trust-reviews`

Body:

```json
{
  "rating_overall": 5,
  "rating_quality": 5,
  "rating_communication": 4,
  "rating_timeliness": 5,
  "would_recommend": true,
  "issue_resolved": true,
  "comment": "Repair outcome confirmed."
}
```

Requires a repair user, enterprise user or admin with access to the repair case.

### List trust reviews for completion report

`GET /api/v1/completion-reports/{id}/trust-reviews`

### Provider quality score detail

`GET /api/v1/providers/{id}/quality-score`

### Provider quality score list

`GET /api/v1/provider-quality-scores`

### Provider trust signals

`GET /api/v1/providers/{id}/trust-signals`

### Provider trust reviews

`GET /api/v1/providers/{id}/trust-reviews`

## Domain events

- `trust.review_recorded`
- `provider.trust_signal_recorded`
- `provider.quality_score_updated`

## Prototype UI

Updated files:

- `public/prototype/index.html`
- `public/prototype/assets/js/app.js`
- `public/prototype/assets/js/api-client.js`
- `public/prototype/assets/js/state.js`

New route:

- `#/trust`

The UI shows:

- completion report link
- trust review state
- provider quality score
- trust tier
- quality / reliability / communication / timeliness metrics
- provider trust signals timeline

## Testing

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-provider-trust-quality.ps1
```

Expected output:

```text
Provider trust and quality smoke test passed.
```

## MVP limitations

- Trust formula is deterministic and simple.
- No dispute workflow yet.
- No fraud detection yet.
- Provider identity is still demo-level and not mapped to real provider accounts.
- Quality score is not yet used as a weight inside Provider Match Engine.

## Next step

Step 18 should connect trust to provider matching and operational governance:

**Provider Ranking Feedback & Marketplace Governance v1**
