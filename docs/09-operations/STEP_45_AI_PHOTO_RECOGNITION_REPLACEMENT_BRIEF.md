# Step 45 — AI Photo Recognition, Replacement-Part Brief & Guided Missing Inputs v1

## Goal

Step 45 connects the simplified Step 44 photo/file moment to a real AI provider integration pattern. The user uploads one or more photos and Re-born produces a first-look replacement-part brief instead of exposing technical recognition jobs.

The UX promise is intentionally simple:

> Upload a photo. Re-born identifies the probable part and asks only for the missing details needed to generate or produce the replacement.

## Provider architecture

The first live provider is OpenAI Vision through the Responses API. The integration is deliberately configurable and safe for CI:

- `OPENAI_API_KEY` empty: deterministic fallback mode, no external calls.
- `OPENAI_API_KEY` present: Re-born sends up to 3 image inputs to OpenAI and requests structured JSON.
- provider errors: recognition still completes with an explicit fallback result and error metadata.

The configured result is normalized into the same internal shape used by the existing repair path decision engine.

## Environment variables

```env
AI_PHOTO_RECOGNITION_PROVIDER=openai
AI_PHOTO_RECOGNITION_ENABLED=true
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_VISION_MODEL=gpt-5.4-mini
OPENAI_TIMEOUT_SECONDS=60
OPENAI_VISION_MAX_IMAGES=3
OPENAI_VISION_MAX_IMAGE_BYTES=5242880
```

## New backend pieces

- `config/ai.php`
- `src/AI/Application/PhotoRecognitionGateway.php`
- `src/AI/Application/OpenAIPhotoRecognitionGateway.php`
- `GET /api/v1/ai/photo-recognition/status`
- existing `POST /api/v1/repair-cases/{id}/recognition-jobs` now calls the provider gateway before fallback.

## Structured output

Recognition results now include:

- `recognition_mode`
- `ai_provider`
- `object_guess.object_context`
- `damage_assessment`
- `replacement_part_brief`
- `recommended_next_step`
- `suggested_inputs`
- `repair_notes`

The key new object is `replacement_part_brief`, containing:

- plain-language summary;
- probable function;
- part family;
- manufacturing candidate flag;
- material hint;
- critical dimensions;
- photo requirements;
- user questions.

## Frontend impact

The Step 2 screen is now framed as:

> Step 2 of 4 · AI photo recognition

The visible flow remains simple:

1. upload photo/file;
2. click **AI identify part**;
3. receive AI first look + replacement brief;
4. continue to generate the replacement route.

No governance, provider routing, quality gate or AI pipeline terminology is shown to the first-time user.

## Smoke test

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-photo-recognition-replacement-brief.ps1 -BaseUrl http://127.0.0.1:8080
```

Expected CI result:

```text
Smoke scripts: 36
smoke-ai-photo-recognition-replacement-brief.ps1
Step 45 release evidence generated. Quality gate passed.
```

## Guardrail

AI recognition is never treated as manufacturing approval. Generated or printed parts still require dimensional validation, material validation and human/provider review before real production.
