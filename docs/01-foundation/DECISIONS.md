# Product and Architecture Decisions

Questo file registra le decisioni strategiche di Re-born.

## Decision 001 — Categoria

Re-born è posizionata come **Repair Intelligence Platform**, non come marketplace STL o service di stampa 3D.

## Decision 002 — Tesi UX

L'utente non cerca un file. L'utente vuole che l'oggetto torni a funzionare.

## Decision 003 — Core engine

Repair Journey, Repair DNA e Knowledge Graph confluiscono nel **Repair Intelligence Engine™**.

## Decision 004 — Motori interni

Il Repair Intelligence Engine™ è composto da:

- Recognition Engine;
- Knowledge Engine;
- Decision Engine;
- Learning Engine;
- Trust Engine.

## Decision 005 — Bounded context

I domini principali sono:

1. Identity Domain
2. Repair Domain
3. AI Domain
4. Marketplace Domain
5. Provider Domain
6. Knowledge Domain
7. Wallet Domain
8. Company Domain

## Decision 006 — Stack tecnico iniziale

- PHP 8.3+
- HTML5
- CSS3
- Vanilla JavaScript
- SQLite in sviluppo
- MariaDB/MySQL in produzione
- Clean Architecture
- SOLID
- Repository Pattern
- Domain Events

## Decision 007 — Ordine di sviluppo

Non si parte dal codice. L'ordine è:

1. Product Book
2. PRD
3. UX Bible
4. Design System
5. Wireframe
6. Mockup UI
7. Prototype
8. Backend
9. Frontend

## Decision 008 — Regola business

Ogni funzione deve rafforzare almeno uno tra:

- Knowledge Graph;
- AI Learning;
- Community;
- Marketplace Liquidity;
- Enterprise Value;
- Sustainability Impact;
- Objects Saved.
