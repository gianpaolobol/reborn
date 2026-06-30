# Step 48.2 — Gemini external curl HTTP-0 body acceptance hotfix

## Problem

On some Windows/PHP setups, Re-born cannot use PHP cURL or PHP HTTPS streams and falls back to `curl.exe`. The command can write a valid Gemini response body to disk while the parsed HTTP status is `0`. Previous code treated that as a transport failure and discarded a valid `candidates` response.

## Fix

`GeminiGooglePhotoRecognitionGateway` now accepts an external-curl response with HTTP status `0` when the body is valid JSON, contains Gemini `candidates`, and does not contain an `error` object.

## Expected result

The live debug script should return `recognition_mode: gemini_vision_api` or `gemini_vision_api_quality_retry` for valid Gemini responses.

## Safety

If the response body contains an `error` object, Re-born still treats it as a provider failure and uses an explicit fallback.
