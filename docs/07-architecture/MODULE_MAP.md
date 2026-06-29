# Module Map

## Root namespaces

Suggested PHP namespace root:

```text
Reborn\
```

---

## Domain modules

```text
src/
  Identity/
    Domain/
    Application/
    Infrastructure/
    Interface/
  Repair/
    Domain/
    Application/
    Infrastructure/
    Interface/
  AI/
    Domain/
    Application/
    Infrastructure/
    Interface/
  Knowledge/
    Domain/
    Application/
    Infrastructure/
    Interface/
  Marketplace/
    Domain/
    Application/
    Infrastructure/
    Interface/
  Provider/
    Domain/
    Application/
    Infrastructure/
    Interface/
  Wallet/
    Domain/
    Application/
    Infrastructure/
    Interface/
  Company/
    Domain/
    Application/
    Infrastructure/
    Interface/
```

---

## Layer responsibilities

### Domain

- entities;
- value objects;
- domain services;
- domain events;
- invariants.

### Application

- use cases;
- command handlers;
- query handlers;
- DTOs;
- transaction orchestration.

### Infrastructure

- database repositories;
- external APIs;
- file storage;
- mail;
- payment gateways;
- AI providers.

### Interface

- HTTP controllers;
- CLI commands;
- API resources;
- view models.

---

## Initial implementation rule

Even if the first implementation is simple PHP, do not mix domain rules into views or controllers.
