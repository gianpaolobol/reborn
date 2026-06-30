# Step 44 — FixPart Benchmark, Repair-First Offer Architecture & Replacement-Part Wizard v1

## Purpose

Step 44 simplifies Re-born around the user need identified after testing the Step 43 demo: a non-expert does not want to understand governance states, STL marketplaces, AI adapters, provider routing or admin dashboards. The user wants a broken object to work again, usually by obtaining or generating a replacement component.

The benchmark lesson from spare-parts catalogues such as FixPart is clear:

- catalogue search is strong when the user already knows the model and spare-part code;
- Re-born must start earlier, when the user only has a broken part, a photo and uncertainty;
- the primary journey must explain how to get a replacement part, not expose internal platform architecture.

## User-facing offer

Plain-language promise:

> Start with one photo or description. Re-born identifies the broken part, checks whether it can be found, and guides generation or production when it cannot.

## Four-step flow

```text
1. Problem
2. Photos & files
3. Generate part
4. Quote
```

### 1. Problem

The user describes the object or broken part. They do not need to know the component name, serial code, material, CAD format or repair workflow.

### 2. Photos & files

The user adds a phone photo, size reference or optional STL/STEP/PDF. The copy explicitly says that CAD files are optional.

### 3. Generate part

Re-born ranks the routes:

- find existing spare;
- generate replacement part;
- involve maker/CAD help;
- route to a provider.

### 4. Quote

Only after the part route is clear does the user see provider/maker options and quote actions.

## What changed

- top navigation reduced to the repair-first journey;
- old technical labels replaced by plain-language labels;
- primary home route repositioned around generating a missing replacement part;
- three offer outcomes added: existing spare, generated part, provider/maker production;
- advanced consoles remain available but grouped away from the user path;
- smoke suite verifies the presence of the Step 44 UX markers.

## Non-goals

Step 44 does not implement real CAD generation, real AI inference, real supplier catalogue integrations, real payments or production logistics. It prepares the correct user experience and offer architecture so those capabilities can be added without confusing first-time users.
