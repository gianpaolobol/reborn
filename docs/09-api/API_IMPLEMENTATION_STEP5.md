# API Implementation — Step 5

## Response envelope

Every response follows the same envelope:

```json
{
  "success": true
}
```

Errors follow:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The request contains invalid or missing fields.",
    "fields": {}
  }
}
```

## Create repair case

```http
POST /api/v1/repair-cases
Content-Type: application/json
```

```json
{
  "title": "Broken Garmin strap connector",
  "description": "The strap connector is cracked.",
  "category": "wearable"
}
```

## Diagnose repair case

```http
POST /api/v1/repair-cases/{id}/diagnose
```

This invokes:

```text
Recognition Engine
Knowledge Engine
Decision Engine
Provider Matching
Domain Events
```

The output is intentionally close to the Step 4 prototype structure so the frontend can be connected in Step 6.
