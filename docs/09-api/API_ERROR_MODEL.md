# API Error Model

All API errors should use a consistent format.

```json
{
  "error": {
    "code": "repair_case_not_found",
    "message": "The repair case could not be found.",
    "details": {},
    "request_id": "req_123"
  }
}
```

---

## Error categories

- validation_error;
- authentication_required;
- permission_denied;
- not_found;
- conflict;
- unsupported_file;
- ai_processing_failed;
- provider_unavailable;
- payment_failed;
- rate_limited;
- internal_error.

---

## UX rule

API errors must be translatable into human actions.

Bad:

```json
{"error":"invalid"}
```

Good:

```json
{
  "error": {
    "code": "photo_too_blurry",
    "message": "The uploaded photo is too blurry to analyze. Upload a sharper photo of the broken component."
  }
}
```
