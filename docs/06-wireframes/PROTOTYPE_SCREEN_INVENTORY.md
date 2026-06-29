# Prototype Screen Inventory

This document connects the MVP static prototype to the UX Bible and PRD.

## Screen list

| ID | Route | User role | Primary question answered |
|---|---|---|---|
| PR-001 | `#/` | Visitor | What is Re-born? |
| PR-002 | `#/start` | Customer | How do I start repairing my object? |
| PR-003 | `#/capture` | Customer | What evidence does Re-born need? |
| PR-004 | `#/diagnosis` | Customer | What object/part was recognized? |
| PR-005 | `#/repair-paths` | Customer | What is the best repair path? |
| PR-006 | `#/part-detail` | Customer | Is there a verified repair model? |
| PR-007 | `#/ai-generation` | Customer | What happens if no model exists? |
| PR-008 | `#/provider-network` | Customer | Who can produce or repair it locally? |
| PR-009 | `#/checkout` | Customer | What am I confirming? |
| PR-010 | `#/account` | Customer | What happened to my repairs and credits? |
| PR-011 | `#/provider` | Provider | What job am I accepting? |
| PR-012 | `#/maker` | Maker | How do I earn from repair models? |
| PR-013 | `#/enterprise` | Enterprise | How does this scale to fleets? |
| PR-014 | `#/admin-ops` | Internal | What needs review before scaling? |

## UX validation checklist

Each screen must pass these checks:

- The user understands what object or repair is being discussed.
- The next action is visible and concrete.
- The screen avoids generic marketplace language.
- Risk, cost, ETA and trust are visible when decisions are requested.
- AI is never presented as magic; it is bounded by validation.
- The Knowledge Graph learning loop is visible somewhere in the journey.
- Maker, provider and platform incentives are legible.
