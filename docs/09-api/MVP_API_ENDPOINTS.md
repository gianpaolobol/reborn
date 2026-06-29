# MVP API Endpoints

The MVP may render server-side HTML first, but backend actions must be designed as API-like contracts.

## Response envelope

```json
{
  "ok": true,
  "data": {},
  "error": null,
  "meta": {}
}
```

Error response:

```json
{
  "ok": false,
  "data": null,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Some fields are invalid.",
    "fields": {
      "email": "Email is required."
    }
  },
  "meta": {}
}
```

---

## Auth

### POST /auth/register

Creates a user.

Request:

```json
{
  "email": "user@example.com",
  "password": "secret",
  "primary_role": "repair_user"
}
```

Response:

```json
{
  "id": "usr_...",
  "email": "user@example.com",
  "primary_role": "repair_user"
}
```

### POST /auth/login

Creates a session.

### POST /auth/logout

Destroys the session.

---

## Repair cases

### POST /repair-cases

Creates a draft repair case.

Request:

```json
{
  "object_name": "coffee machine knob"
}
```

Response:

```json
{
  "id": "rc_...",
  "public_ref": "RB-000001",
  "status": "draft"
}
```

### PATCH /repair-cases/{id}

Updates description and metadata.

Request:

```json
{
  "description": "The knob broke inside and no longer turns the steam valve.",
  "brand": "ExampleBrand",
  "model": "X100",
  "dimensions_note": "Approx 35 mm diameter",
  "material_clues": "black plastic"
}
```

### POST /repair-cases/{id}/photos

Uploads one photo.

Multipart fields:

- `photo`;
- `photo_role` optional.

### POST /repair-cases/{id}/submit

Submits the case for classification.

Response:

```json
{
  "id": "rc_...",
  "status": "submitted",
  "repair_dna_id": "dna_..."
}
```

### GET /repair-cases/{id}/diagnosis

Returns diagnosis summary.

### GET /repair-cases/{id}/paths

Returns possible paths.

### POST /repair-cases/{id}/paths/select

Request:

```json
{
  "path_type": "provider_quote"
}
```

---

## Admin classification

### PATCH /admin/classifications/{repairCaseId}

Request:

```json
{
  "category_id": "cat_small_appliances",
  "product_type_id": "ptype_coffee_machine",
  "component_id": "comp_knob",
  "damage_type_id": "damage_broken_connector",
  "confidence": "medium",
  "safety_level": "normal",
  "notes": "Likely printable plastic knob, heat resistance should be checked."
}
```

Response:

```json
{
  "repair_case_id": "rc_...",
  "classification_status": "classified"
}
```

---

## Providers and quotes

### POST /providers

Creates or updates provider profile.

### POST /repair-cases/{id}/quote-requests

Creates quote request.

Request:

```json
{
  "provider_id": "prov_...",
  "message": "Can you print or redesign this knob?"
}
```

### POST /provider/quotes

Creates quote.

Request:

```json
{
  "quote_request_id": "qr_...",
  "price_cents": 2400,
  "currency": "EUR",
  "turnaround_note": "3 business days",
  "material_note": "PETG or ASA after review",
  "provider_notes": "Need one extra measurement before production."
}
```

---

## Makers, models and bounties

### POST /makers

Creates or updates maker profile.

### POST /models

Submits model metadata.

### POST /repair-cases/{id}/bounties

Creates a bounty placeholder.

### POST /maker/bounties/{id}/submissions

Submits candidate model metadata for a bounty.

---

## Outcomes

### POST /repair-cases/{id}/outcome

Request:

```json
{
  "outcome": "success",
  "notes": "The printed part fits and the object works again.",
  "final_photo_path": null
}
```

Response:

```json
{
  "repair_case_id": "rc_...",
  "outcome": "success",
  "knowledge_signal_recorded": true
}
```

---

## Metrics

### GET /admin/metrics

Returns MVP funnel and graph metrics.

Response:

```json
{
  "cases_created": 100,
  "cases_submitted": 72,
  "cases_classified": 60,
  "paths_selected": 42,
  "outcomes_recorded": 18,
  "successful_repairs": 11,
  "knowledge_signals": 240
}
```
