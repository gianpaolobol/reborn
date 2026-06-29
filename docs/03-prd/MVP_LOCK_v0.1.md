# MVP Lock v0.1

## Purpose

This document freezes the first executable scope of Re-born.

The MVP is not the final platform. It is the minimum product capable of proving the central thesis:

> A broken object can be converted into structured Repair Intelligence and routed toward a useful repair path.

## MVP thesis

The MVP must prove five things:

1. users are willing to describe a broken object through guided input;
2. Re-born can transform that input into Repair DNA;
3. the system can propose one or more repair paths;
4. makers/providers/admins can act on structured cases;
5. each outcome improves the Knowledge Graph.

## In scope

### Public and identity

- homepage / landing page;
- register/login;
- basic account roles: repair user, maker, provider, admin;
- profile basics.

### Repair case

- start repair case;
- upload photos;
- describe issue;
- provide dimensions, brand/model, material clues;
- create Repair DNA draft;
- show repair case timeline;
- show status and next action.

### Classification

- manual-first classification with AI-ready structure;
- product category;
- product type;
- component candidate;
- damage type;
- confidence level;
- missing data prompts.

### Repair paths

- existing CAD/model path;
- provider print quote path;
- maker bounty path;
- spare part path placeholder;
- AI generation path placeholder;
- expert review path placeholder.

### Maker

- maker profile;
- model metadata upload;
- link model to category/component;
- respond to bounty;
- royalty/credit placeholders.

### Provider

- provider profile;
- capabilities, machines, materials, service area;
- receive qualified request;
- send quote;
- update order/request status.

### Admin

- review cases;
- correct classifications;
- approve models;
- approve provider/maker profiles;
- inspect outcomes;
- update taxonomy.

### Knowledge Graph

- store structured entities;
- store relationships;
- store outcome feedback;
- retain learning signals even when repair fails.

## Out of scope

The following must not block the MVP:

- fully autonomous CAD generation;
- production wallet and payment system;
- escrow;
- shipping integrations;
- enterprise portal;
- white-label;
- native mobile app;
- public API marketplace;
- advanced trust algorithm;
- advanced provider routing;
- machine capacity optimization;
- legal automation for every country.

## MVP categories

The MVP should start with a narrow repair domain. Suggested first category:

**Plastic replacement parts for household objects and small appliances.**

Reason:

- high 3D-printability;
- common breakage;
- understandable user photos;
- suitable for providers;
- manageable safety/liability;
- strong sustainability story.

Initial examples:

- knobs;
- clips;
- covers;
- handles;
- feet;
- brackets;
- hinges;
- small enclosures;
- non-critical mechanical parts.

Excluded from MVP category:

- medical parts;
- automotive safety components;
- electrical high-voltage parts;
- food-contact parts unless clearly labelled as experimental;
- structural load-bearing critical parts;
- weapons or regulated items.

## MVP completion loop

A real MVP demo must complete this chain:

```text
Broken object
  -> repair case
  -> Repair DNA
  -> classification
  -> repair path
  -> provider/maker/model action
  -> outcome feedback
  -> Knowledge Graph update
```

## Definition of ready

A feature can enter implementation only when it has:

- user story;
- acceptance criteria;
- screen or state reference;
- data entities;
- API endpoint or backend service reference;
- failure state;
- analytics event.

## Definition of done

A feature is done only when:

- happy path works;
- empty state exists;
- error state exists;
- permission rules are enforced;
- data is persisted;
- an audit or event log is created where relevant;
- it contributes to a metric or Knowledge Graph signal.
