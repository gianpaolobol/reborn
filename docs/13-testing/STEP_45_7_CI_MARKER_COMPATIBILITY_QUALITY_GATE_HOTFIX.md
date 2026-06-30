# STEP 45.7 — CI Marker Compatibility & Quality Gate Hotfix

## Problema

La pipeline GitHub Actions falliva nella fase `Verify CI smoke guard markers` perché il workflow cercava ancora il marker legacy:

```text
STEP45_RELEASE_EVIDENCE_WITH_AI_PHOTO_RECOGNITION_V1
```

mentre `scripts/ci-release-evidence.ps1` era stato aggiornato con un marker hotfix successivo:

```text
STEP45_5_1_RELEASE_EVIDENCE_CI_LOCALIZATION_HOTFIX_V1
```

La conseguenza era che il workflow usciva con errore prima della smoke suite completa. Poiché `ci-release-evidence.ps1` gira con `if: always()`, il quality gate veniva poi generato in assenza di risultati smoke completi e falliva correttamente.

## Correzione

- Aggiornato `scripts/ci-release-evidence.ps1` con marker hotfix:

```text
STEP45_7_CI_MARKER_COMPATIBILITY_QUALITY_GATE_HOTFIX_V1
```

- Mantenuto esplicitamente il marker legacy come commento di compatibilità:

```text
STEP45_RELEASE_EVIDENCE_WITH_AI_PHOTO_RECOGNITION_V1
```

- Aggiornati i check grep in `.github/workflows/smoke-tests.yml` per accettare sia il marker legacy sia il marker Step 45.7.

## File modificati

```text
.github/workflows/smoke-tests.yml
scripts/ci-release-evidence.ps1
docs/13-testing/STEP_45_7_CI_MARKER_COMPATIBILITY_QUALITY_GATE_HOTFIX.md
```

## Validazione attesa

```text
Verify CI smoke guard markers: passed
Run full smoke test suite: passed
Generate release evidence and quality gate: passed
```

## Nota operativa

Questo hotfix non modifica logica AI, UX, database, API o smoke test funzionali. Serve solo a riallineare i marker CI dopo la catena di hotfix Step 45.x.
