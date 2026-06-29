# 08 — Repair Knowledge Graph

The Knowledge Graph is the strategic data asset of Re-born.

It connects repair-relevant entities and turns every repair attempt into reusable intelligence.

---

## Core nodes

- User;
- Company;
- Product;
- ProductCategory;
- Brand;
- Model;
- Component;
- DamageType;
- RepairCase;
- RepairPath;
- CADModel;
- CADModelVersion;
- Maker;
- Provider;
- Material;
- ManufacturingMethod;
- Order;
- SparePart;
- Instruction;
- Review;
- Outcome;
- Bounty;
- WalletTransaction.

---

## Core relationships

- Product HAS_COMPONENT Component;
- Component BELONGS_TO Product;
- Component CAN_BE_REPLACED_BY SparePart;
- Component CAN_BE_PRINTED_FROM CADModel;
- CADModel HAS_VERSION CADModelVersion;
- CADModel CREATED_BY Maker;
- RepairCase TARGETS Product or Component;
- RepairCase USED RepairPath;
- RepairPath PRODUCED Outcome;
- Provider CAN_PRODUCE Material / Method;
- Provider FULFILLED Order;
- Outcome VALIDATES CADModelVersion;
- Outcome UPDATES_TRUST Provider / Maker / Model;
- Bounty REQUESTS CADModel;
- WalletTransaction REWARDS Maker or Provider.

---

## Why graph, not only relational tables

Relational tables are useful for persistence, transactions and API implementation.

The graph concept is needed because repair depends on relationships:

- component compatibility;
- model reuse;
- repair outcomes;
- provider-material capability;
- brand/model variants;
- maker reputation;
- field evidence.

The initial MVP can store graph-like data in MariaDB with explicit relationship tables. A dedicated graph database can be evaluated later.

---

## Learning rule

A repair case without outcome feedback is incomplete.

The system must consistently ask for outcome verification because that is what makes the Knowledge Graph valuable.
