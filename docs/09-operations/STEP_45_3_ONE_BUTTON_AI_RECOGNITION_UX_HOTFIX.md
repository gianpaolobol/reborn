# STEP 45.3 — One-Button AI Photo Recognition UX Hotfix

## Why this hotfix exists

Manual testing showed that Step 2 still felt too technical for a non-expert user. The screen exposed separate actions for upload and AI recognition, so the user could upload a photo but not understand what to press next or why no immediate AI output appeared.

The intended user experience is simpler:

> Click one button, choose the photo, and receive either a clear part recognition or a clear request for additional images.

## What changed

- The Step 2 action is now a single CTA: **Carica foto e identifica il pezzo**.
- The file picker input is hidden behind that single CTA.
- After image selection, the prototype automatically uploads the photo and starts AI recognition.
- The user no longer needs to press a second **AI identify part** button.
- Recognition results are simplified into two states:
  - **pezzo riconosciuto**: probable part, function, manufacturability hint, material hint and next action;
  - **servono altre immagini**: the AI asks for concrete additional views such as side view, full object context, or scale reference.
- The Step 2 UI avoids technical wording such as recognition jobs, provider routing or internal pipeline states.
- The previous upload and recognition functions remain available for internal compatibility, but the visible user flow is one-button first.

## User-facing rule

The Step 2 screen must never leave a beginner unsure what happened. After choosing a photo, the UI must show one of these outcomes:

1. **Sto analizzando la foto…**
2. **Pezzo probabilmente riconosciuto**
3. **Non ho ancora abbastanza elementi: carica queste immagini**
4. **Errore leggibile con prossima azione**

## Manual validation

1. Start the local PHP server.
2. Open `http://127.0.0.1:8080/prototype/index.html#/repair-guide`.
3. Login as `repair.user@reborn.local / password` if needed.
4. Create or use a repair case.
5. Go to Step 2.
6. Click **Carica foto e identifica il pezzo**.
7. Select a JPG/PNG/WebP image.
8. Confirm that upload and AI recognition run automatically.

## Smoke validation

The existing Step 45 smoke test now checks the one-button UX markers:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ai-photo-recognition-replacement-brief.ps1 -BaseUrl http://127.0.0.1:8080
```

Expected result:

```text
Step 45 AI photo recognition replacement brief smoke test passed.
```
