# MVP Implementation Plan

## Principle

Build the MVP as a modular monolith using PHP 8.3+, Clean Architecture and DDD boundaries.

Do not start with microservices. Re-born needs strong domain clarity before distribution.

## Layers

```text
/public
  index.php
/src
  /Shared
    /Domain
    /Application
    /Infrastructure
    /Presentation
  /Identity
  /Repair
  /AI
  /Marketplace
  /Provider
  /Knowledge
  /Wallet
  /Company
/database
/tests
```

## MVP modules to implement first

### 1. Shared

- EntityId value object;
- DateTime provider;
- Result object;
- DomainEvent interface;
- EventDispatcher;
- simple router;
- request/response abstraction;
- repository contracts.

### 2. Identity

- User;
- Role;
- authentication service;
- session service;
- password hashing;
- middleware.

### 3. Repair

- RepairCase;
- RepairPhoto;
- RepairDNA;
- Classification;
- RepairPath;
- RepairOutcome;
- repair application services.

### 4. Provider

- ProviderProfile;
- ProviderCapability;
- QuoteRequest;
- Quote;
- provider application services.

### 5. Marketplace / Maker

- MakerProfile;
- ModelContribution;
- Bounty;
- review status;
- model approval service.

### 6. Knowledge

- ProductCategory;
- ProductType;
- Component;
- DamageType;
- KnowledgeSignal;
- relationship writer.

### 7. Admin

Admin is not a domain. It is a presentation/application surface over the domains.

## Build sequence

1. basic router and layout;
2. database connection and migrations;
3. Identity;
4. Repair Case creation;
5. photo upload;
6. guided details;
7. admin classification;
8. repair paths;
9. provider quote;
10. maker/model/bounty metadata;
11. outcome feedback;
12. knowledge signal writer;
13. metrics dashboard.

## Avoid

- payment-first implementation;
- AI-first implementation;
- marketplace search-first implementation;
- framework lock-in before domain clarity;
- complex frontend state management;
- premature microservices.
