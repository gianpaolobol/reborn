# 06 — Repair Journey Framework™

The Repair Journey Framework™ is the standard operating model for every repair case.

---

## Stage 1 — Start Repair

The user initiates a repair case.

Required UX:

- ask what is broken;
- allow photo upload;
- allow text description;
- reduce fear of technical complexity.

System output:

- repair case created;
- initial status: `draft` or `intake_started`.

---

## Stage 2 — Identify Object

The system tries to identify:

- product category;
- brand;
- model;
- component;
- visible damage;
- missing data.

Output must include confidence and uncertainty.

---

## Stage 3 — Diagnose Need

The system determines the need type:

- replacement part;
- repair instruction;
- CAD model;
- AI-generated model;
- provider assistance;
- manual expert review;
- non-repairable or not safe.

---

## Stage 4 — Find Solution

The system searches:

- internal model library;
- compatible parts;
- maker models;
- previous repair cases;
- provider inventory;
- AI generation options;
- bounty candidates.

---

## Stage 5 — Choose Path

Possible repair paths:

1. download verified model;
2. buy existing component;
3. request local print;
4. send to provider;
5. generate AI model;
6. open maker bounty;
7. ask community;
8. reject repair with explanation.

---

## Stage 6 — Produce / Obtain

The solution is obtained through:

- download;
- provider order;
- shipped spare part;
- pickup;
- bounty delivery;
- enterprise workflow.

---

## Stage 7 — Repair Execution

The user receives:

- instructions;
- warnings;
- required tools;
- photos;
- fit checks;
- material notes;
- provider guidance.

---

## Stage 8 — Verify Outcome

The system collects:

- success/failure;
- photos after repair;
- fit rating;
- durability rating;
- provider rating;
- model rating;
- notes.

---

## Stage 9 — Learn

The Learning Engine updates:

- model trust;
- provider trust;
- component compatibility;
- repair instructions;
- recognition training signals;
- Knowledge Graph edges.
