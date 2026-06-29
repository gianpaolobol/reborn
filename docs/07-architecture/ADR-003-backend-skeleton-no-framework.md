# ADR-003 — Backend Skeleton Without Framework

## Status

Accepted for MVP skeleton.

## Context

Re-born must remain understandable, portable and controllable during early product discovery. The project needs Clean Architecture and DDD boundaries before it needs framework convenience.

## Decision

The Step 5 backend uses PHP 8.3 without a framework.

It includes:

- custom router
- front controller
- PSR-4-style autoloader
- application services
- repositories
- domain events
- SQLite development database

## Consequences

Positive:

- maximum transparency
- low dependency risk
- easy migration to a framework later if necessary
- strong architectural discipline from day one

Negative:

- authentication, validation and middleware must be built or adopted later
- more boilerplate than Laravel/Symfony

## Reversal condition

A framework can be introduced only if it does not compromise:

- DDD boundaries
- module isolation
- API contract stability
- prototype velocity
