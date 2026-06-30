# STEP 45.4 — Adaptive One-Button Recognition & Italian-First Bilingual UX Hotfix

## Purpose

Step 45.4 corrects the Step 2 UX so the photo-recognition flow behaves like a guided repair assistant rather than a technical upload console.

The user requirement is explicit:

- the user must have one button to upload and identify the photo;
- if one photo is not enough, the same button must change to **Carica altre immagini**;
- the user must not be asked to use a second/new button for the retry;
- all default user-facing text must be Italian;
- the site must offer Italian/English, with Italian as the default language.

## UX behavior

Initial state:

```text
Carica foto e identifica il pezzo
```

After the user uploads a photo and the AI cannot identify the part confidently:

```text
Carica altre immagini
```

The AI result panel explains which images are missing, for example:

- foto frontale ravvicinata;
- foto laterale;
- foto del pezzo montato o della zona rotta;
- foto con righello o moneta per la scala.

There is no secondary upload/retry button inside the AI result panel. The result panel only explains the result and points the user back to the main CTA.

## Files changed

```text
public/prototype/index.html
public/prototype/assets/js/app.js
public/prototype/assets/css/reborn.css
scripts/smoke-ai-photo-recognition-replacement-brief.ps1
README.md
docs/09-operations/STEP_45_4_ADAPTIVE_ONE_BUTTON_BILINGUAL_UX_HOTFIX.md
```

## Technical changes

- Added `REBORN_I18N` lightweight dictionary.
- Added `currentLanguage()`, `setLanguage()`, `t()` and `languageSwitch()` helpers.
- Added Italian-first static chrome synchronization through `syncStaticChromeLanguage()`.
- Added `currentPhotoCtaLabel()` to derive CTA text from recognition state.
- Removed the extra “Carica altre immagini e riprova” button from the insufficient-evidence AI panel.
- The hidden file input remains internal; the visible UX is one CTA.
- Updated smoke markers to verify Italian one-button behavior and bilingual support markers.

## Validation

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-photo-recognition-replacement-brief.ps1 -BaseUrl http://127.0.0.1:8080
```

Expected:

```text
Step 45 AI photo recognition replacement brief smoke test passed.
```
