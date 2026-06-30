# STEP 45.6 — CI Localization & Release Evidence Hotfix

## Scope

This hotfix stabilizes the CI suite after the Step 45.4/45.5 UX changes that made Italian the default prototype language.

## Fixes

- `smoke-guided-user-repair-experience.ps1` now accepts both the old English markers and the new default Italian navigation markers.
- `smoke-repair-first-offer-architecture.ps1` now accepts both English and Italian four-step navigation markers.
- `ci-release-evidence.ps1` no longer fails with `Property "smoke_script" cannot be found` when the smoke summary contains failed results or when matrix rows are ordered dictionaries.
- The release evidence script now reports failed release-blocking rows using a safe property accessor.

## Why

The product decision remains correct: Italian is the default language, with English available through the bilingual UI. The CI was still checking some older English-only static markers from Step 43/44, so it failed even though the UX change was intentional.

## Expected result

After this patch:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\ci-smoke-tests.ps1 -BaseUrl http://127.0.0.1:8080
powershell -ExecutionPolicy Bypass -File .\scripts\ci-release-evidence.ps1 -BaseUrl http://127.0.0.1:8080
```

Expected:

```text
Smoke scripts: 36
Step 45 release evidence / quality gate passed
```

## Safety

No runtime database migrations are added. No `.env` or secrets are included. `.env.example` must keep `OPENAI_API_KEY=` empty.
