# Next Agent Handoff — Step 8

## Obiettivo

Step 8 ha introdotto il primo livello Identity & Access MVP per Re-born.

## Stato attuale

Sono disponibili:

- utenti
- password hash
- sessioni bearer token
- token hash persistiti
- login
- registrazione
- me
- logout
- ruoli
- autorizzazione admin
- utenti demo
- smoke test PowerShell
- feature test PHP

## Decisioni prese

- Nessun framework esterno.
- Token bearer API-first, non cookie session.
- Token plain mostrato solo una volta; database salva hash SHA-256.
- Ruoli iniziali: `repair_user`, `maker`, `provider`, `enterprise`, `admin`.
- Registrazione pubblica consentita solo per `repair_user`, `maker`, `provider`.
- Enterprise/admin sono provisioning interno.

## Domande aperte

- Quando introdurre email verification?
- Quando introdurre password reset?
- Quando introdurre MFA per admin/provider?
- Come collegare repair case ownership al flusso UX senza rompere il prototipo pubblico?
- Come modellare permessi più granulari rispetto ai ruoli?

## Prossimi passi

1. Step 9 — Repair Case Ownership & User Dashboard.
2. Collegare repair case all’utente autenticato.
3. Separare dashboard user/maker/provider/admin.

## Vincoli

- Non rompere il prototipo statico.
- Non introdurre framework.
- Mantenere DDD e Clean Architecture.
- Tutte le API devono mantenere l’error model Step 7.
