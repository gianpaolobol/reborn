# Step 4 — Prototype Pack

## Purpose

Step 4 turns the Re-born MVP documentation into a first navigable static prototype.

The prototype is not intended as production code. It is an alignment artifact for product, UX, architecture and future implementation.

## Location

```text
public/prototype/index.html
```

## Included screens

| Route | Screen | Purpose |
|---|---|---|
| `#/` | Overview | Explain Re-born as Repair Intelligence Platform |
| `#/start` | Repair intake | Start from broken object, not file upload |
| `#/capture` | Photos and dimensions | Collect visual/dimensional evidence |
| `#/diagnosis` | Recognition result | Show Recognition Engine output |
| `#/repair-paths` | Repair paths | Show Decision Engine ranking |
| `#/part-detail` | Verified repair model | Present CAD as repair asset |
| `#/ai-generation` | AI fallback | Use AI only when knowledge is missing |
| `#/provider-network` | Provider selection | Rank local production options |
| `#/checkout` | Repair order | Confirm repair outcome, provider and wallet events |
| `#/account` | User dashboard | Show object history, credits and impact |
| `#/provider` | Provider PRO | Provider accepts constrained repair jobs |
| `#/maker` | Maker CAD marketplace | Upload model, earn royalty from fulfilled repairs |
| `#/enterprise` | Enterprise portal | Position fleet and white-label use cases |
| `#/admin-ops` | Internal ops | Surface graph, trust and AI validation queues |

## Product decisions embodied in the prototype

1. The first action is **Start a repair**, not **Search STL**.
2. CAD files are presented as **repair assets**, not as downloadable collectibles.
3. AI generation is a fallback path requiring validation.
4. Providers are ranked by trust, constraints and repair fit, not only price.
5. Maker royalty is tied to fulfilled repairs.
6. Wallet and credits are connected to repair outcomes and ecosystem value.
7. The internal ops view makes Knowledge Graph and Trust Engine maintenance explicit.

## Technical constraints

- No frontend framework.
- No external dependency.
- No build step.
- No backend integration.
- No real upload.
- No real payment.
- No production authentication.

## Next step

Step 5 should create the first backend skeleton aligned to the prototype:

1. PHP 8.3 Clean Architecture folder structure.
2. Domain entities for Repair Request, Repair DNA, Provider Quote, CAD Model, Wallet Transaction.
3. SQLite schema wired to repositories.
4. Basic HTTP routing without framework or with a minimal internal router.
5. JSON API responses matching the MVP API contract.
