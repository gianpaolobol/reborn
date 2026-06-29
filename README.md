# Re-born

**Re-born** è una **Repair Intelligence Platform** internazionale.

Mission: **Allow anyone to repair anything.**

Re-born non è un marketplace di STL. È un sistema operativo per la riparazione: identifica prodotti e componenti rotti, propone il percorso migliore, recupera o genera ricambi, coordina maker e provider locali, gestisce pagamenti/royalty e apprende da ogni riparazione.

> L'utente non cerca uno STL. Vuole che il proprio oggetto torni a funzionare.

Repository ufficiale: https://github.com/gianpaolobol/reborn

---

## Stato del progetto

Fase attuale: **strategic product definition / Re-born OS bootstrap**.

Sono già presenti le basi di:

- Product Book;
- PRD MVP;
- UX Bible;
- Design System;
- Domain Driven Design;
- architettura applicativa;
- database schema concettuale;
- API contract concettuale;
- roadmap;
- handoff master.

Il documento madre è:

```text
PRODUCT.md
```

---

## Struttura repository

```text
reborn/
├── PRODUCT.md
├── README.md
├── docs/
│   ├── 00-master-index/
│   ├── 01-foundation/
│   ├── 02-product-book/
│   ├── 03-prd/
│   ├── 04-ux-bible/
│   ├── 05-design-system/
│   ├── 06-wireframes/
│   ├── 07-architecture/
│   ├── 08-database/
│   ├── 09-api/
│   ├── 10-security/
│   ├── 11-frontend/
│   ├── 12-backend/
│   ├── 13-testing/
│   ├── 14-roadmap/
│   ├── 15-investor/
│   ├── 16-operations/
│   └── 17-handoff/
├── src/
├── public/
├── database/
├── tests/
└── scripts/
```

---

## Principio guida

Ogni modifica deve rafforzare almeno uno di questi asset:

- Repair Knowledge Graph;
- AI Learning;
- Community;
- Marketplace Liquidity;
- Enterprise Value;
- Sustainability Impact;
- Objects Saved.

---

## Roadmap immediata

1. Pubblicare il repository su GitHub.
2. Consolidare Product Book.
3. Scrivere PRD completo con user stories e acceptance criteria.
4. Progettare UX Bible con sitemap 100+ schermate.
5. Definire Design System e primi wireframe.
6. Solo dopo: prototipo e codice.

---

## Comandi Git iniziali

```powershell
git add .
git commit -m "docs: bootstrap Re-born OS"
git push -u origin main
```

Se GitHub richiede autenticazione:

```powershell
winget install --id GitHub.cli
gh auth login
git push -u origin main
```
