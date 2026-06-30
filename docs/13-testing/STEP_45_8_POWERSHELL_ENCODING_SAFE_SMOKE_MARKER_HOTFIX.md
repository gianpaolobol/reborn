# Step 45.8 — PowerShell Encoding-Safe Smoke Marker Hotfix

## Scope

This hotfix addresses a false negative in:

```text
scripts/smoke-ai-photo-recognition-replacement-brief.ps1
```

The smoke test previously asserted the exact Italian UI marker:

```text
Testo letto nell’immagine
```

On Windows PowerShell 5.1, UTF-8 scripts without BOM can be interpreted as ANSI, causing the curly apostrophe to become mojibake in the expected marker string:

```text
Testo letto nellâ€™immagine
```

The application UI was correct; the smoke marker was not encoding-safe.

## Change

The test now uses ASCII-safe partial markers:

```text
Testo letto
immagine
```

This preserves coverage that the Step 45.5 OCR/reference-image UI is present, without depending on a Unicode punctuation character inside a PowerShell source string.

## Files changed

```text
scripts/smoke-ai-photo-recognition-replacement-brief.ps1
README.md
docs/13-testing/STEP_45_8_POWERSHELL_ENCODING_SAFE_SMOKE_MARKER_HOTFIX.md
```

## Expected result

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-photo-recognition-replacement-brief.ps1 -BaseUrl http://127.0.0.1:8080
```

Should no longer fail with:

```text
Missing Step 45.4 prototype marker: Testo letto nellâ€™immagine
```

## Product impact

No product behavior changes. The user still sees the Italian label:

```text
Testo letto nell’immagine
```

The one-button AI recognition UX remains unchanged.
