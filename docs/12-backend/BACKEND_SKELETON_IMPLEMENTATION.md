# Backend Skeleton Implementation

## Runtime

- PHP 8.3+
- SQLite for local development
- MariaDB/MySQL-ready configuration for production
- No frontend framework
- No PHP framework at this stage

## Folder map

```text
bootstrap/
  app.php
  autoload.php
config/
  app.php
  database.php
  routes.php
src/
  Shared/
  Repair/
  AI/
  Knowledge/
  Marketplace/
  Provider/
  Identity/
  Wallet/
  Company/
public/
  index.php
storage/
  database/
  logs/
database/
  migrations/
  seeds/
scripts/
  setup-dev.php
  doctor.php
```

## Bounded contexts implemented in Step 5

### Repair Domain

Owns repair cases, status transitions and repair case lifecycle.

### AI Domain

Contains the first mock `RecognitionEngine`. This will later wrap image recognition, CAD reconstruction, external AI APIs or internal models.

### Knowledge Domain

Contains the first mock `KnowledgeEngine`, connected to `knowledge_nodes`.

### Marketplace Domain

Owns repair path decisions and future CAD/component/provider commercial logic.

### Provider Domain

Owns distributed manufacturing provider matching.

## Rule for future work

Do not let API routes contain business logic. Routes call controllers. Controllers call application services. Application services coordinate repositories, engines and domain events.
