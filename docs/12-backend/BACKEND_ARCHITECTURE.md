# Backend Architecture

## Stack

- PHP 8.3+;
- SQLite for development;
- MariaDB/MySQL for production;
- Clean Architecture;
- DDD-inspired modular monolith.

---

## Initial modules

- Identity;
- Repair;
- Knowledge;
- Marketplace;
- Provider;
- Wallet;
- AI;
- Admin.

---

## First use cases

- CreateRepairCase;
- UploadRepairAsset;
- ClassifyRepairCase;
- RecommendRepairPath;
- SelectRepairPath;
- CreateProviderRequest;
- UploadCADModel;
- SubmitRepairOutcome.

---

## Persistence

Use repository interfaces in the application/domain boundary.

Infrastructure implementations can use PDO initially.
