# STEP 45.1 — AI Photo Recognition Live API Reliability Hotfix

## Why this hotfix exists

During manual testing with a real `OPENAI_API_KEY`, the Step 2 "AI identify part" action could appear to do nothing after uploading a photo. The most likely causes were:

- the prototype client timeout was only ~4.2 seconds, too short for a live Vision request;
- failed or empty recognition jobs were not surfaced clearly in the Step 2 UI;
- strict structured-output schema keywords were more complex than necessary for a first live integration;
- there was no operator script to isolate API key/model/network/upload issues.

## What changed

- Frontend API timeout now supports per-request timeouts.
- Upload requests get 45 seconds.
- AI photo recognition requests get 90 seconds.
- The Step 2 UI now shows "AI is analyzing the photo" immediately while the live request is running.
- Failed or incomplete recognition jobs now show a visible "Recognition needs attention" panel instead of silently leaving the user in the same state.
- OpenAI provider errors are sanitized and shown in the fallback result as visible diagnostics.
- The strict JSON schema was simplified for broader OpenAI structured-output compatibility.
- Added `scripts/debug-ai-photo-recognition-live.ps1` for local troubleshooting with a real image.
- Updated default `OPENAI_TIMEOUT_SECONDS` to 60 seconds.

## Manual debug command

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-photo-recognition-live.ps1 -BaseUrl http://127.0.0.1:8080 -ImagePath C:\path\to\photo.jpg
```

Expected outcomes:

- `Recognition mode: openai_vision_api` means live API worked.
- `Recognition mode: fallback_after_openai_error` means Re-born recovered but OpenAI returned an error; the provider error is printed.
- `Recognition job status is failed` means backend processing failed before a fallback result could be created; check PHP server output and `storage/logs`.

## User-facing rule

The user must never click AI recognition and receive silence. The UI must show one of three states:

1. analyzing;
2. replacement brief ready;
3. recognition needs attention with concrete checks.
