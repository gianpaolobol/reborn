# Step 48.4 — Deterministic Upload Recognition Smoke

The generic upload/recognition smoke test validates the Re-born repair upload pipeline, attachment listing, recognition job persistence and domain events.

After enabling live Gemini Vision, this generic CI test could spend real provider time/quota and time out on a 1x1 placeholder PNG. Step 48.4 adds `recognition_mode=deterministic_smoke` to the request body and routes that request through the deterministic local recognition path in non-production environments only.

Live image quality is still validated separately with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-vision-quality-live.ps1 -BaseUrl http://127.0.0.1:8080 -ImagePath "C:\REBORN\reborn\test-images\165314-dishwasher-wheel.jpg.jpg"
```

Expected live result for the reference image: `recognition_mode: gemini_vision_api`, part number `165314`, commercial name `Dishwasher Lower Rack Wheel`.
