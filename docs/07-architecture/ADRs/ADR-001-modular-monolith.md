# ADR-001 — Start as a Modular Monolith

## Status

Accepted

## Context

Re-born has multiple domains: Identity, Repair, AI, Marketplace, Provider, Knowledge, Wallet and Company. These could later become separate services, but the early risk is domain confusion, not infrastructure scale.

## Decision

The MVP will be built as a modular monolith in PHP 8.3+.

Each bounded context will have internal boundaries, but deployment remains one application.

## Consequences

### Positive

- faster MVP development;
- simpler deployment;
- easier refactoring;
- lower operational overhead;
- stronger domain learning before service extraction.

### Negative

- requires discipline to avoid module coupling;
- future extraction must be planned through interfaces and domain events.

## Guardrails

- no direct database access across modules;
- communicate across contexts through application services or domain events;
- keep namespace boundaries clean;
- avoid global utility dumping grounds.
