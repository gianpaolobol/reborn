# API Error Model — Step 7

All API responses keep backward compatibility with the MVP prototype by returning a top-level `success` boolean.

## Success

```json
{
  "success": true,
  "repair_case": {},
  "meta": {
    "request_id": "..."
  }
}
```

## Validation error

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The request contains invalid or missing fields.",
    "details": {
      "fields": {
        "title": ["title is required."]
      }
    }
  },
  "meta": {
    "request_id": "..."
  }
}
```

## Standard codes

- `BAD_REQUEST`
- `VALIDATION_ERROR`
- `NOT_FOUND`
- `METHOD_NOT_ALLOWED`
- `SERVER_ERROR`

## Request IDs

The router accepts `X-Request-Id` or generates a short random request id. The value is returned in `meta.request_id` and used in daily API logs.
