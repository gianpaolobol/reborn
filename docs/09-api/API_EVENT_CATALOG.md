# API and Analytics Event Catalog

## Identity

- `user.registered`
- `user.logged_in`
- `user.logged_out`
- `role.updated`

## Repair

- `repair_case.created`
- `repair_case.photo_uploaded`
- `repair_case.description_added`
- `repair_case.details_added`
- `repair_case.submitted`
- `repair_case.diagnosis_viewed`
- `repair_case.path_selected`
- `repair_case.outcome_recorded`

## Classification

- `classification.created`
- `classification.updated`
- `classification.corrected`
- `classification.low_confidence_flagged`

## Provider

- `provider.profile_created`
- `provider.profile_reviewed`
- `quote.requested`
- `quote.created`
- `quote.accepted_placeholder`
- `quote.rejected_placeholder`

## Maker / model

- `maker.profile_created`
- `maker.profile_reviewed`
- `model.submitted`
- `model.reviewed`
- `bounty.created`
- `bounty.submission_created`

## Knowledge

- `knowledge.signal_recorded`
- `knowledge.entity_created`
- `knowledge.relationship_created`

## Safety

- `safety.case_flagged`
- `safety.case_blocked`
- `safety.disclaimer_viewed`

## Metrics requirements

Every event must include:

- event id;
- event name;
- timestamp;
- user id if available;
- entity type;
- entity id;
- payload JSON;
- request/session id where possible.
