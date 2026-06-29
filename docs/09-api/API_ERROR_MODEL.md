# API — Error Model

## Standard error response

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "REPAIR_CASE_NOT_FOUND",
    "message": "Repair case not found.",
    "details": {},
    "request_id": "req_123"
  }
}
```

## Error categories

- AUTHENTICATION_ERROR
- AUTHORIZATION_ERROR
- VALIDATION_ERROR
- UPLOAD_ERROR
- AI_JOB_ERROR
- LOW_CONFIDENCE_RESULT
- NOT_FOUND
- CONFLICT
- RATE_LIMITED
- PAYMENT_ERROR
- WALLET_ERROR
- PROVIDER_UNAVAILABLE
- INTERNAL_ERROR

## Regola UX

Ogni errore API che arriva al frontend deve poter essere tradotto in una frase umana e in un'azione successiva.
