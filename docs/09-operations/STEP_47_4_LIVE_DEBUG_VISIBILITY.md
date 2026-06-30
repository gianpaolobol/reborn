# STEP 47.4 — Live AI Vision Debug Visibility Hotfix

This hotfix does not change production recognition behavior. It makes `scripts/debug-ai-vision-quality-live.ps1` print the full recognition API response before failing.

Purpose:

- distinguish HTTP/API failure from recognition job failure;
- expose `recognition_job.error_message`;
- fail explicitly when OpenAI Vision falls back;
- prevent ambiguous `Recognition failed.` output during live tests.

Expected live success marker:

```text
recognition_mode: openai_vision_api
```

or:

```text
recognition_mode: openai_vision_api_quality_retry
```

If the script prints `error_fallback`, the OpenAI call was attempted but failed. The provider error must be fixed before UX recognition quality can be evaluated.
