# Step 45 Smoke — AI Photo Recognition & Replacement-Part Brief

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-photo-recognition-replacement-brief.ps1 -BaseUrl http://127.0.0.1:8080
```

The smoke test verifies:

- health capabilities expose Step 45;
- repair user can read the AI photo-recognition provider status;
- upload + recognition still works without a live API key;
- recognition result includes provider metadata and a replacement-part brief;
- the prototype exposes the non-expert AI first-look copy.

CI intentionally runs without `OPENAI_API_KEY`, so no external OpenAI call is made unless explicitly configured in the environment.
