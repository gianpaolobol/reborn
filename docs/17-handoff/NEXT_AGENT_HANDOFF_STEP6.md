# Next Agent Handoff — Step 6

## Obiettivo

Step 6 ha collegato il prototipo statico MVP alle prime API PHP del backend Re-born.

Il prototipo ora è API-aware: usa dati live quando il server PHP è attivo e passa automaticamente in mock mode quando viene aperto come file statico o quando l'API non risponde.

## Stato attuale

Sono stati aggiornati:

```text
public/prototype/index.html
public/prototype/assets/js/api-client.js
public/prototype/assets/js/state.js
public/prototype/assets/js/app.js
public/prototype/assets/css/reborn.css
```

Sono stati aggiunti documenti di delivery, frontend contract, API contract e QA checklist.

## Decisioni prese

- Nessun framework frontend.
- Nessun build step.
- Il prototipo deve restare apribile anche senza backend.
- L'integrazione API deve essere progressiva e reversibile.
- Il fallback mock è obbligatorio per evitare demo rotte.
- Il primo flusso live è: intake → repair_case → diagnosis → repair_paths/providers.

## Domande aperte

- Quando introdurre autenticazione e sessioni?
- Quando rendere reale l'upload immagini?
- Quando introdurre CSRF/token di sicurezza per le azioni POST?
- Quando sostituire il mock Recognition Engine con un servizio AI reale?
- Quando introdurre il vero Trust Engine per ranking provider/maker/modelli?

## Prossimi passi

1. Costruire Step 7 — Backend Persistence Hardening:
   - repository più completi;
   - validazione più rigorosa;
   - error model uniforme;
   - prime feature test.

2. Aggiungere upload pipeline:
   - immagini;
   - dimensioni;
   - allegati STL/STEP;
   - storage locale di sviluppo.

3. Preparare autenticazione MVP:
   - utenti;
   - ruoli;
   - provider;
   - maker;
   - admin.

## Vincoli

- PHP 8.3+
- SQLite sviluppo
- MariaDB produzione futura
- Vanilla JavaScript
- No framework frontend
- DDD modulare
- UX orientata alla riparazione, non alla ricerca di file STL
- Ogni funzione deve alimentare almeno uno tra Knowledge Graph, AI learning, marketplace liquidity, provider network, sustainability impact o objects saved
