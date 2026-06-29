# Backend Architecture — Re-born

## Stack

- PHP 8.3+
- Clean Architecture
- DDD
- Repository Pattern
- Service Layer
- Domain Events
- SQLite dev
- MariaDB/MySQL prod

## Struttura proposta

```text
src/
  Identity/
    Domain/
    Application/
    Infrastructure/
    Presentation/
  Repair/
  AI/
  Knowledge/
  Marketplace/
  Provider/
  Wallet/
  Company/
  Shared/
    Database/
    Events/
    Http/
    Security/
    Validation/
public/
  index.php
database/
  migrations/
  seeds/
tests/
```

## Regola

I controller non devono contenere logica di business. La logica vive in Application Services e Domain Services.
