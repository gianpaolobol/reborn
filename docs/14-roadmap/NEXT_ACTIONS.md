# Next Actions

## Immediate priority

1. Push repository to GitHub.
2. Review and approve `PRODUCT.md`.
3. Review Product Book v0.1.
4. Freeze MVP wedge.
5. Convert PRD v0.1 into implementation tickets.

---

## Step 1 — Repository truth

Complete GitHub authentication and push:

```powershell
winget install --id GitHub.cli
gh auth login
git push -u origin main
```

---

## Step 2 — Product approval

Review:

- `PRODUCT.md`;
- `docs/02-product-book/*`;
- `docs/03-prd/PRD_v0.1.md`.

Decide:

- first repair categories;
- MVP monetization path;
- provider onboarding requirements;
- maker reward model;
- AI provider strategy.

---

## Step 3 — UX wireframes

Create low-fidelity wireframes for:

1. homepage;
2. start repair;
3. upload photos;
4. diagnosis summary;
5. repair path selector;
6. provider request;
7. maker upload;
8. provider dashboard;
9. admin repair review;
10. outcome confirmation.

---

## Step 4 — Technical skeleton

Only after product/UX baseline:

- create PHP folder skeleton;
- implement routing;
- implement config/env;
- implement database migrations;
- implement RepairCase entity and repository;
- implement first intake flow.

## Step 4 completed — Prototype Pack

The first navigable static prototype now exists in `public/prototype/index.html`.

Immediate next action: create the backend skeleton and connect the MVP API contract to PHP 8.3 domain modules.
