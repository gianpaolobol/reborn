# Testing Strategy — Re-born

## Livelli

1. Unit test per Domain Services.
2. Integration test per repository e database.
3. API test per endpoint principali.
4. UX flow test manuali per Repair Journey.
5. Security test su upload, auth, ruoli e wallet.
6. Data quality test su Knowledge Graph.

## Test MVP obbligatori

- creazione utente;
- creazione repair case;
- upload immagine;
- risultato AI low confidence;
- selezione repair path;
- upload modello maker;
- registrazione provider;
- richiesta preventivo;
- ordine base;
- calcolo royalty;
- validazione riparazione;
- aggiornamento Repair Score.

## Regola

Un flusso non è completato se non misura l'esito della riparazione.
