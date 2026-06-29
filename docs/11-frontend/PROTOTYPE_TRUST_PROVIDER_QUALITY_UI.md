# Prototype Trust & Provider Quality UI

## Route

`#/trust`

## Purpose

The page explains that provider reputation in Re-born is earned through completed repairs, not generic marketplace activity.

## User flow

1. Complete a repair fulfilment.
2. Record completion learning on `#/learning`.
3. Open `#/trust`.
4. Repair user, enterprise user or admin records a provider trust review.
5. UI displays provider quality score and trust signals.

## Live API methods

Added to `api-client.js`:

- `createTrustReview(completionReportId, data)`
- `getTrustReviews(completionReportId)`
- `getProviderQualityScore(providerId)`
- `getProviderQualityScores()`
- `getProviderTrustSignals(providerId)`
- `getProviderTrustReviews(providerId)`

## State fields

Added to `REBORN_STATE.api`:

- `trustReviews`
- `trustReview`
- `providerQualityScores`
- `providerQualityScore`
- `providerTrustSignals`

## UX guardrail

The copy avoids presenting trust as a social rating system. The provider score is framed as a repair outcome quality signal.
