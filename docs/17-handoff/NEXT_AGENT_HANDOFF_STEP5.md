# Handoff — Step 5 Backend Skeleton

## Objective

Continue Re-born from a static prototype into a working MVP backend while preserving the strategic identity of a Repair Intelligence Platform.

## Current state

Step 5 added a PHP 8.3 backend skeleton with:

- front controller
- JSON API router
- SQLite setup
- migrations and seed data
- Repair Case API
- mock Recognition Engine
- mock Knowledge Engine
- mock Repair Path Decision Service
- mock Provider Matching Service
- persisted domain events

## Important files

```text
public/index.php
bootstrap/app.php
config/routes.php
src/Repair/Presentation/RepairController.php
src/Repair/Application/DiagnoseRepairCaseService.php
src/AI/Application/RecognitionEngine.php
src/Knowledge/Application/KnowledgeEngine.php
src/Marketplace/Application/RepairPathDecisionService.php
src/Provider/Application/ProviderMatchingService.php
database/migrations/001_create_reborn_mvp_schema.sql
database/seeds/001_mvp_seed.sql
scripts/setup-dev.php
```

## Next step recommendation

Step 6 should connect the static prototype to the API.

Priority tasks:

1. Replace mock arrays in `public/prototype/assets/js/prototype-data.js` with API calls.
2. Add intake form submission to `POST /api/v1/repair-cases`.
3. Add diagnosis action to `POST /api/v1/repair-cases/{id}/diagnose`.
4. Render repair paths and providers from API responses.
5. Preserve graceful fallback if the backend is not running.

## Constraints

Do not add Bootstrap, frontend frameworks or heavy dependencies.

Do not turn Re-born into a generic marketplace.

Every backend feature must increase at least one asset:

- Knowledge Graph
- AI Learning
- Community
- Marketplace Liquidity
- Enterprise Value
- Sustainability Impact
- Objects Saved
