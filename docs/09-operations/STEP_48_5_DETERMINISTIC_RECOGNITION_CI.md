# STEP 48.5 — Deterministic recognition jobs for CI smoke tests

Generic CI smoke scripts must not call live Gemini/OpenAI providers. They verify backend contracts, persistence and domain events, not model quality.

Updated scripts:

- `scripts/smoke-repair-upload-recognition.ps1`
- `scripts/smoke-repair-path-decision.ps1`
- `scripts/smoke-provider-match-quote.ps1`
- `scripts/smoke-ai-photo-recognition-replacement-brief.ps1`

Each script sends:

```json
{
  "attachment_ids": ["..."],
  "recognition_mode": "deterministic_smoke"
}
```

This mode is allowed only outside production and returns a CI-safe result with `ai_provider.status=ci_safe_no_external_ai_call`.

Real provider quality remains validated with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-vision-quality-live.ps1 `
  -BaseUrl http://127.0.0.1:8080 `
  -ImagePath "C:\REBORN\reborn\test-images\165314-dishwasher-wheel.jpg.jpg"
```
