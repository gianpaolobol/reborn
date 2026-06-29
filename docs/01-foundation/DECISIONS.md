# Re-born — Strategic Decisions

This document records decisions that should not be casually reversed.

---

## D001 — Re-born is a Repair Intelligence Platform

Re-born is not a model repository, not a marketplace clone, and not a generic 3D printing service.

It is a platform that guides a user from a broken object to the best repair path.

---

## D002 — The product starts from repair intent

The user journey starts with:

> What do you need to repair?

Not with:

> Upload an STL.

Marketplace, CAD and printing are downstream solutions, not the primary UX frame.

---

## D003 — Repair Intelligence Engine is the core system

Repair Journey, Repair DNA and Knowledge Graph are unified into the Repair Intelligence Engine™.

The engine is composed of:

- Recognition Engine;
- Knowledge Engine;
- Decision Engine;
- Learning Engine;
- Trust Engine.

---

## D004 — DDD is the architecture language

The initial bounded contexts are:

- Identity;
- Repair;
- AI;
- Marketplace;
- Provider;
- Knowledge;
- Wallet;
- Company.

---

## D005 — Initial stack is intentionally simple

Backend:

- PHP 8.3+;
- Clean Architecture;
- Repository Pattern;
- Service Layer;
- Domain Events.

Frontend:

- HTML5;
- CSS3;
- Vanilla JavaScript.

Database:

- SQLite for development;
- MariaDB/MySQL for production.

---

## D006 — No frontend framework at the beginning

The first version must remain simple, fast, inspectable and controllable.

This does not prevent future adoption of a framework if the product reaches a complexity level that justifies it.

---

## D007 — Visual language must be proprietary

Do not use Bootstrap, purchased templates, generic purple SaaS gradients, heavy glassmorphism or crypto-style UI.

The visual language must be minimal, precise, technical and trust-oriented.

---

## D008 — Every feature must grow a strategic asset

A feature is useful only if it improves at least one of these:

- Knowledge Graph;
- AI learning;
- community;
- marketplace liquidity;
- enterprise value;
- sustainability impact;
- objects saved.
