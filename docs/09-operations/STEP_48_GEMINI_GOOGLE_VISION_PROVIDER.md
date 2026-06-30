# Step 48.1 — Gemini-only Vision Provider Router

Google Cloud Vision API has been removed from the active Re-born recognition flow.

## Goal

Use Gemini API directly for image understanding, OCR-like text reading, part identification and repair brief generation.

```env
AI_PHOTO_RECOGNITION_PROVIDER=auto
AI_PHOTO_RECOGNITION_ENABLED=true
AI_VISION_PROVIDER_ORDER=gemini,openai

GEMINI_API_KEY=
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_VISION_MODEL=gemini-2.5-flash
GEMINI_TIMEOUT_SECONDS=90
GEMINI_MAX_OUTPUT_TOKENS=4096
GEMINI_TEMPERATURE=0.1
```

## Runtime flow

1. Load uploaded JPG/PNG/WebP images.
2. Send images directly to Gemini.
3. Ask Gemini to read all visible text and identify the part.
4. Normalize the response into Re-born repair JSON.
5. Retry once if the first answer is generic.
6. If Gemini fails, return a clearly marked fallback.

## Acceptance markers

- `gemini_vision_provider`
- `gemini_vision_api`
- `gemini_vision_api_quality_retry`
- `gemini_vision_repair_identification_v1`
- `AI_VISION_PROVIDER_ORDER=gemini,openai`

## Removed requirement

The following variables are no longer required for the active flow:

```text
GOOGLE_CLOUD_VISION_ENABLED
GOOGLE_CLOUD_VISION_API_KEY
GOOGLE_CLOUD_VISION_BASE_URL
GOOGLE_CLOUD_VISION_FEATURES
GOOGLE_CLOUD_VISION_TIMEOUT_SECONDS
```
