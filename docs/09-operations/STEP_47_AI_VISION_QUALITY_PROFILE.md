# Step 47 — Maximum Vision Recognition Quality Profile v1

## Goal

Upgrade Re-born image recognition so product/detail/reference images are treated as useful repair evidence, not as generic object photos.

The key correction is that OCR and product text must dominate the first identification. A product image that says `165314 Dishwasher Lower Rack Wheel` must become:

```text
Ruota del cestello inferiore per lavastoviglie
Codice pezzo: 165314
Funzione: scorrimento del cestello inferiore
Prossimo passo: verificare ricambio commerciale, poi brief maker se non disponibile
```

It must not be collapsed into:

```text
cover / scocca plastica
```

## Changes

- Default model changed to `gpt-5.5` for maximum vision/OCR quality.
- Image detail changed to `original` by default.
- Timeout increased to 90 seconds for first live calls.
- Max image count increased to 8.
- Max image payload increased to 20 MB.
- Optional Responses API `web_search` enabled by default for visible part numbers/product titles.
- Prompt profile upgraded to `reference_image_ocr_part_identification_v2`.
- Strict JSON schema now includes commercial name, possible brands/models, compatibility clues, manufacturing features and external lookup summary.
- A quality retry is attempted when the first response is too generic or low confidence.
- Fallback messaging now explicitly states that local fallback cannot read OCR, brand, model or code from the photo.

## Configuration

```env
AI_PHOTO_RECOGNITION_PROVIDER=openai
AI_PHOTO_RECOGNITION_ENABLED=true
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_VISION_MODEL=gpt-5.5
OPENAI_TIMEOUT_SECONDS=90
OPENAI_VISION_MAX_IMAGES=8
OPENAI_VISION_MAX_IMAGE_BYTES=20971520
OPENAI_VISION_DETAIL=original
OPENAI_VISION_WEB_SEARCH_ENABLED=true
OPENAI_REASONING_EFFORT=high
OPENAI_VISION_MAX_OUTPUT_TOKENS=4500
```

## Acceptance

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-vision-quality-profile.ps1 -BaseUrl http://127.0.0.1:8080
powershell -ExecutionPolicy Bypass -File .\scripts\ci-smoke-tests.ps1 -BaseUrl http://127.0.0.1:8080
```

The smoke test is static/contract-level and API-key safe. It verifies that the code is configured for the upgraded prompt, detail, web search, quality retry and UI disclosure.

## Important note

ChatGPT Plus and the OpenAI API are different products. Plus helps inside the ChatGPT app. API usage is billed/configured separately and requires a valid API key and API billing/credits.
