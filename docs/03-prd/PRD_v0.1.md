# Re-born PRD v0.1

## Product

Re-born Repair Intelligence Platform

## Version

0.1 — foundational PRD for MVP definition

---

## 1. Problem

People often throw away objects because they cannot identify a broken component, find a compatible spare part, model it, or locate someone who can produce it.

The current ecosystem is fragmented:

- model repositories require search knowledge;
- providers require ready files;
- spare-part sites are incomplete;
- AI 3D tools are not repair-specific;
- repair knowledge is scattered and unverified.

---

## 2. Product goal

Create an MVP that allows a user to start a repair case, upload photos and descriptions, receive a structured repair path, and connect to at least one solution type: existing model, maker request, provider quote or AI-assisted model generation placeholder.

---

## 3. MVP outcome

The MVP should prove that Re-born can convert a repair problem into structured data and route it to a useful next step.

MVP success is not “fully automated repair for everything”.

MVP success is:

- users can start repair cases;
- cases become structured Repair DNA;
- models/providers/makers can be connected;
- outcomes can be recorded;
- the Knowledge Graph starts accumulating value.

---

## 4. Primary users

- repair user;
- maker;
- provider;
- admin/operator.

Enterprise is documented but not included in the first MVP implementation unless needed for pilot demos.

---

## 5. MVP core features

### User side

- create repair case;
- upload photos;
- add description;
- add known brand/model/dimensions;
- receive repair path status;
- view suggested solutions;
- request provider quote;
- request maker model/bounty;
- confirm repair outcome.

### Maker side

- create maker profile;
- upload CAD model metadata;
- link model to repair category/component;
- respond to bounty/request;
- see basic earnings/credits placeholder.

### Provider side

- create provider profile;
- define capabilities, location, materials, machines;
- receive qualified repair/print request;
- send quote;
- mark order status.

### Admin side

- review repair cases;
- edit classifications;
- approve models;
- manage providers;
- inspect feedback;
- manage categories/components.

---

## 6. Non-goals for MVP

- full autonomous AI CAD generation;
- full payment/wallet production system;
- enterprise portal;
- white label;
- API marketplace;
- mobile app;
- advanced logistics;
- fully automated legal/IP moderation;
- real-time provider capacity optimization.

These can be stubbed or documented, but not built as core MVP.

---

## 7. MVP user journey

1. User opens “Start a Repair”.
2. User uploads photos and describes the issue.
3. System creates a Repair Case.
4. System asks guided questions.
5. Admin/AI/manual logic classifies product and component.
6. System shows possible repair paths.
7. User chooses one path.
8. Maker/provider/admin action progresses the case.
9. User receives solution.
10. User confirms success/failure.
11. Knowledge Graph is updated.

---

## 8. Data to capture from day one

- category;
- product;
- brand;
- model;
- component;
- damage type;
- photos;
- dimensions;
- material assumptions;
- selected repair path;
- maker/provider/model involved;
- outcome;
- success/failure reason.

---

## 9. Acceptance definition

The MVP is acceptable when a real user can complete this loop:

> broken object → repair case → structured diagnosis → suggested solution → provider/model/maker route → outcome feedback.

---

## 10. Open questions

- Which first product categories should be supported?
- Will early AI recognition be external API, manual-assisted or hybrid?
- Which payment flow should be implemented first: direct commission, wallet or credits?
- How strict should model approval be before public visibility?
- What liability language is required for functional parts?
