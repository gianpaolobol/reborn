# API Contract — Conceptual v0.1

Base path:

```text
/api/v1
```

---

## Repair cases

### Create repair case

```http
POST /api/v1/repair-cases
```

Request:

```json
{
  "title": "Broken dishwasher basket wheel",
  "description": "The wheel broke and the basket does not slide.",
  "known_brand": "ExampleBrand",
  "known_model": "ABC123"
}
```

Response:

```json
{
  "id": "repair_case_id",
  "status": "intake_started",
  "next_action": "upload_photos"
}
```

### Upload repair asset

```http
POST /api/v1/repair-cases/{id}/assets
```

### Get repair diagnosis

```http
GET /api/v1/repair-cases/{id}/diagnosis
```

### Select repair path

```http
POST /api/v1/repair-cases/{id}/repair-paths/{pathId}/select
```

### Submit outcome

```http
POST /api/v1/repair-cases/{id}/outcome
```

---

## Models

```http
GET /api/v1/models
POST /api/v1/models
GET /api/v1/models/{id}
POST /api/v1/models/{id}/versions
```

---

## Providers

```http
GET /api/v1/providers
POST /api/v1/providers
POST /api/v1/provider-requests
POST /api/v1/provider-requests/{id}/quote
POST /api/v1/provider-requests/{id}/accept
```

---

## Bounties

```http
GET /api/v1/bounties
POST /api/v1/bounties
POST /api/v1/bounties/{id}/submissions
```

---

## Wallet

```http
GET /api/v1/wallet
GET /api/v1/wallet/transactions
POST /api/v1/credits/purchase
```
