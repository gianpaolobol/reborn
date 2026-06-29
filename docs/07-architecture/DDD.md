# Domain Driven Design

## Bounded contexts

### Identity Domain

Users, roles, authentication, permissions, profiles.

### Repair Domain

Repair cases, repair status, components, diagnosis, repair paths, outcomes.

### AI Domain

Recognition requests, AI results, model generation requests, confidence, corrections.

### Knowledge Domain

Product taxonomy, components, relationships, compatibility, repair knowledge.

### Marketplace Domain

CAD models, downloads, licenses, pricing, royalties.

### Provider Domain

Provider profiles, capabilities, quote requests, orders, fulfilment, ratings.

### Wallet Domain

Credits, balances, transactions, royalties, bounties, payouts.

### Company Domain

Enterprise accounts, assets, teams, private repair cases, reporting.

---

## Aggregate examples

### RepairCase

Owns:

- status;
- target object/component;
- uploaded evidence;
- selected path;
- outcome.

### CADModel

Owns:

- metadata;
- versions;
- verification status;
- license;
- trust score.

### Provider

Owns:

- capabilities;
- materials;
- service area;
- quote rules;
- trust score.

### Wallet

Owns:

- balance;
- ledger entries;
- credit transactions.

---

## Ubiquitous language

Use terms from `docs/01-foundation/GLOSSARY.md` consistently in code, database and UI.
