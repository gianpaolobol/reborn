# Step 46 — User Repair Wizard Simplification QA Checklist

## Goal

Validate that the public prototype is understandable for a base user and guides them from a broken-part photo to the fastest replacement route without exposing technical routing decisions.

## User-facing acceptance checks

- The default prototype route is `#/repair-guide`.
- The primary visible journey is `Foto -> Analisi -> Ricambio`.
- The main navigation is reduced to: repair a part, user requests, help, login, advanced consoles.
- The homepage/wizard has one primary CTA at a time.
- The user can start without knowing the part name.
- Recognition output is normalized into simple user states:
  - `Pezzo riconosciuto`.
  - `Servono altre immagini`.
  - `Non riesco ancora a identificarlo`.
- Minimal missing information is requested only after recognition and includes a `Non lo so` option.
- Provider, maker, governance, routing and quote-engine details are not exposed as decisions in the base flow.
- Advanced tools remain reachable only through `#/advanced`.

## Technical acceptance checks

Run after starting the PHP development server:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-user-repair-wizard-simplification.ps1 -BaseUrl http://127.0.0.1:8080
powershell -ExecutionPolicy Bypass -File .\scripts\ci-smoke-tests.ps1 -BaseUrl http://127.0.0.1:8080
powershell -ExecutionPolicy Bypass -File .\scripts\ci-release-evidence.ps1 -BaseUrl http://127.0.0.1:8080
```

## Notes

Legacy routes `#/start`, `#/capture`, `#/diagnosis`, `#/repair-paths` and `#/provider-network` are intentionally routed to `userRepairWizard` so older links do not reopen the old multi-choice experience.


## Step 46.1 smoke compatibility hotfix

- [x] `scripts/smoke-ai-photo-recognition-replacement-brief.ps1` avoids a rigid marker containing the typographic apostrophe in `Testo letto nell’immagine`.
- [x] The prototype still exposes the full Italian user-facing label `Testo letto nell’immagine`.
- [x] The smoke marker is now ASCII-safe for Windows PowerShell 5.1 while preserving Step 45.4 UX coverage.
