# Application Architecture

## Architectural style

Re-born should use Clean Architecture with DDD-inspired bounded contexts.

The first implementation can be simple, but boundaries must be explicit.

---

## Layers

```text
Interface Layer
  -> Application Layer
      -> Domain Layer
      -> Infrastructure Layer through interfaces
```

The Domain Layer must not depend on database, HTTP, filesystem, AI providers or payment providers.

---

## Request example

### Create repair case

1. HTTP controller receives request.
2. Controller validates basic input shape.
3. Application service executes `CreateRepairCase` use case.
4. Domain entity `RepairCase` is created.
5. Repository persists it.
6. Domain event `RepairCaseCreated` is recorded.
7. Response returns repair case ID and next action.

---

## External integrations

Integrations must be behind interfaces:

- AI provider;
- file storage;
- payment provider;
- email provider;
- geocoding;
- shipping;
- analytics.

This avoids lock-in and makes future provider replacement easier.

---

## MVP architecture priority

Do not over-engineer infrastructure, but do protect the domain model.

A modular monolith is the right initial shape.

Microservices are not appropriate at the beginning.
