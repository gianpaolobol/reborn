# API Contract

Base URL:

```text
/api/v1
```

## Standard response

```json
{
  "success": true,
  "data": {},
  "error": null
}
```

## Endpoints MVP

### Auth

- POST /auth/register
- POST /auth/login

### Repair

- POST /repairs
- GET /repairs/{id}
- POST /repairs/{id}/images

### AI

- POST /ai/recognition
- POST /ai/reconstruction
- GET /ai/jobs/{id}

### Knowledge

- GET /parts/search
- GET /parts/{id}
- POST /parts

### Provider

- POST /providers
- GET /providers/nearby
- POST /providers/{id}/materials

### Marketplace

- POST /quotes
- POST /orders

### Wallet

- GET /wallet
- GET /wallet/transactions
- POST /bounties

### Company

- POST /companies
- POST /companies/{id}/official-parts
