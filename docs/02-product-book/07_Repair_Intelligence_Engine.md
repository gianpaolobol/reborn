# 07 — Repair Intelligence Engine™

The Repair Intelligence Engine™ is the core intelligence system of Re-born.

It is not a single model. It is an orchestration layer that combines AI, structured data, rules, outcomes and trust.

---

## Engine modules

### Recognition Engine

Purpose:

- identify objects, components and visible damage.

Inputs:

- images;
- text;
- dimensions;
- CAD/STL files;
- user corrections;
- previous cases.

Outputs:

- product candidates;
- component candidates;
- confidence score;
- missing information;
- next best question.

### Knowledge Engine

Purpose:

- search and reason over the Knowledge Graph.

Outputs:

- related products;
- known components;
- compatible models;
- previous repairs;
- repair instructions;
- provider availability;
- material suggestions.

### Decision Engine

Purpose:

- select the best repair path.

Decision factors:

- confidence;
- safety;
- cost;
- delivery time;
- model trust;
- provider distance;
- material suitability;
- past success rate;
- user skill level;
- sustainability.

### Learning Engine

Purpose:

- convert outcomes into system improvement.

Signals:

- success/failure;
- model revisions;
- provider ratings;
- fit feedback;
- user corrections;
- returned/failed orders;
- bounty completions.

### Trust Engine

Purpose:

- rank reliability.

Trust dimensions:

- accuracy;
- repair success;
- maker quality;
- provider fulfilment;
- dispute history;
- model version maturity;
- material evidence;
- enterprise verification.

---

## Output contract

Every engine result should include:

- recommended repair path;
- confidence level;
- reasoning summary;
- missing data;
- alternatives;
- risk notes;
- next action.

---

## Example decision

Input:

- broken dishwasher basket wheel;
- user uploads two photos;
- AI identifies component with 82% confidence;
- Knowledge Graph finds three compatible models;
- one model has 94% repair success;
- provider available within 8 km.

Output:

- recommended path: local print verified model;
- material: PETG or Nylon depending on heat/water constraints;
- action: confirm wheel diameter and axle size;
- alternatives: buy compatible commercial spare part or request provider measurement review.
