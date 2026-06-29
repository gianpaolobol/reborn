# Re-born MVP Static Prototype v0.1

This folder contains the first navigable static prototype for Re-born.

## How to open

Open this file in a browser:

```text
public/prototype/index.html
```

No server is required. The prototype uses only:

- HTML5
- CSS3
- Vanilla JavaScript
- Mock data
- Hash routes

## Prototype routes

```text
#/                         Overview
#/start                    Repair intake
#/capture                  Photos and dimensions
#/diagnosis                Recognition result
#/repair-paths             Decision Engine repair paths
#/part-detail              Verified repair model
#/ai-generation            AI fallback path
#/provider-network         Distributed provider selection
#/checkout                 Repair order confirmation
#/account                  User dashboard
#/provider                 Provider PRO view
#/maker                    Maker upload and royalty view
#/enterprise               Enterprise portal preview
#/admin-ops                Internal operations console
```

## Design constraints

The prototype intentionally avoids Bootstrap, purchased templates, heavy glassmorphism and generic SaaS visual language.

It follows the Re-born visual principles:

- Graphite / Off White base
- Repair Green for positive repair actions
- Electric Blue for intelligence and systems
- Safety Orange for warnings, AI and validation
- Max border radius: 4 px
- Dense, functional, industrial interface
- Every screen must reinforce: the user is repairing an object, not browsing a marketplace

## Important note

This is not production code. It is a product and UX alignment artefact used to:

1. validate the MVP journey;
2. align future agents and developers;
3. turn PRD and UX Bible into visible screens;
4. prepare backend and frontend implementation without ambiguity.
