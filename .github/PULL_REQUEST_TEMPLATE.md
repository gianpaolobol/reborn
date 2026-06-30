# Pull Request

## Descrizione

## Tipo di modifica

- [ ] Documentazione
- [ ] UX/UI
- [ ] Backend
- [ ] Frontend
- [ ] Database
- [ ] API
- [ ] Fix

## Checklist

- [ ] La modifica è coerente con la Vision.
- [ ] La modifica rafforza almeno un asset strategico.
- [ ] La documentazione è aggiornata.
- [ ] Nessun segreto/API key è stato committato.
- [ ] La GitHub Actions smoke suite passa o il motivo del fallimento è documentato.
- [ ] Se sono stati aggiunti nuovi smoke test, `scripts/ci-smoke-tests.ps1` è stato aggiornato.
- [ ] La regression test matrix è aggiornata in `scripts/ci-release-evidence.ps1` e `docs/13-testing/REGRESSION_TEST_MATRIX.md`.
- [ ] L’artifact CI `reborn-ci-release-evidence` è disponibile o il motivo dell’assenza è documentato.
- [ ] Se la modifica impatta demo/investor walkthrough, i caveat mock/local/pilot sono espliciti.
- [ ] Se la modifica impatta public pilot/intake, nessun impegno reale verso clienti/provider è implicato senza governance review.
