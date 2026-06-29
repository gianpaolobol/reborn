# Security Baseline — Re-born

## Rischi principali

Re-born gestirà:

- foto caricate dagli utenti;
- possibili immagini di ambienti domestici;
- file CAD/STL/OBJ/STEP;
- modelli potenzialmente coperti da diritti;
- wallet, crediti, royalty e payout;
- provider e indirizzi;
- dati enterprise e cataloghi ufficiali.

## Requisiti MVP

- password hashing sicuro;
- sessioni sicure;
- CSRF protection;
- rate limiting;
- validazione upload;
- controllo MIME e dimensione file;
- scansione/isolamento file sospetti;
- RBAC;
- audit log;
- separazione ruoli user/maker/provider/company/admin;
- nessun segreto nel repository;
- `.env` escluso da Git;
- log senza dati sensibili.

## File upload

Ogni file deve avere:

- validazione estensione;
- validazione MIME;
- limite dimensione;
- nome normalizzato;
- storage fuori dalla public root quando possibile;
- record database;
- stato moderation/scanned.

## AI input safety

Le immagini e descrizioni possono contenere contenuti non ammessi o dati personali. Il sistema deve prevedere moderazione e cancellazione.

## Trust is product

La sicurezza non è solo tecnica. In Re-born diventa fiducia di maker, provider, utenti e aziende.
