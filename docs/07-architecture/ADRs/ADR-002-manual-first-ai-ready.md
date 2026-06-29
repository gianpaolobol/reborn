# ADR-002 — Manual-first, AI-ready Classification

## Status

Accepted

## Context

The platform vision depends on AI recognition and Repair Intelligence. However, building full autonomous AI before capturing real repair cases would create high risk and low learning.

## Decision

The MVP will use a manual-first classification console with AI-ready data structures.

## Consequences

### Positive

- faster launch;
- safer early classifications;
- structured data for future training;
- clearer admin oversight;
- easier debugging of failure cases.

### Negative

- early operations require human review;
- classification throughput is limited;
- UI must be honest about review status.

## Future path

AI services can later suggest category/component/damage, but admin correction remains a learning signal.
