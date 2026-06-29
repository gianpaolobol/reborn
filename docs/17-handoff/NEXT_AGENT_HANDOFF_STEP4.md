# Handoff — Re-born Step 4 Prototype Pack

## Obiettivo

Portare Re-born dal livello PRD/wireframe testuale al primo prototipo statico navigabile, mantenendo il posizionamento di Repair Intelligence Platform.

## Stato attuale

È stato aggiunto un prototipo statico in:

```text
public/prototype/index.html
```

Il prototipo è navigabile tramite hash routes, usa solo HTML/CSS/Vanilla JS e contiene dati mock.

## Decisioni prese

- Nessun framework frontend.
- Nessun Bootstrap.
- Nessun template acquistato.
- AI generation posizionata come fallback, non come esperienza principale.
- Marketplace CAD subordinato al concetto di repair asset.
- Provider ranking centrato su trust, vincoli, materiale, SLA e repair fit.
- Maker royalty legata alle riparazioni completate.

## Schermate incluse

- Overview
- Repair intake
- Capture photos/dimensions
- Diagnosis
- Repair paths
- Verified repair model
- AI generation fallback
- Provider network
- Checkout/repair order
- User dashboard
- Provider PRO
- Maker upload/royalty
- Enterprise
- Admin/Ops

## Domande aperte

- Quale livello di fedeltà visuale è sufficiente prima di iniziare backend?
- Il primo MVP deve includere AI generation reale o solo intake/triage manuale assistito?
- Il marketplace CAD deve essere pubblico già in MVP o solo interno/curato?
- Il wallet deve essere economico reale o inizialmente solo ledger interno?
- Provider indipendenti e service professionali devono avere onboarding differenziato già nel primo MVP?

## Prossimi passi

1. Creare skeleton backend PHP 8.3 con Clean Architecture e moduli DDD.
2. Collegare schema SQLite MVP a repository e seed data.
3. Implementare API JSON mock/realistiche per alimentare il prototipo.

## Vincoli

- PHP 8.3+.
- SQLite sviluppo, MariaDB/MySQL produzione.
- HTML/CSS/Vanilla JS.
- DDD, Repository Pattern, SOLID, Domain Events.
- La UX deve sempre partire dall'oggetto rotto e dal risultato di riparazione.
