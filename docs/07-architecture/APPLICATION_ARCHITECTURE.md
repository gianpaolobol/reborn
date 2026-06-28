# Application Architecture

## Obiettivo

Costruire una piattaforma PHP modulare, scalabile e mantenibile, con logica di dominio separata da controller e persistenza.

## Pattern

- Front Controller
- Router
- Controller Layer
- Application Services
- Domain Services
- Repository Pattern
- Domain Events
- DTO/Request Validation

## Struttura prevista

```text
app/
  Domains/
    Repair/
    AI/
    Knowledge/
    Marketplace/
    Provider/
    Wallet/
    Identity/
    Company/
  Shared/
    Database/
    Http/
    Events/
    Security/
    Validation/
public/
  index.php
```
