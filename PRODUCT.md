# Re-born — Product Operating System

**Versione:** 0.2  
**Data:** 2026-06-29  
**Repository:** https://github.com/gianpaolobol/reborn  
**Mission:** Allow anyone to repair anything.

---

## 1. Definizione sintetica

Re-born è una **Repair Intelligence Platform** internazionale.

Non è un semplice marketplace di file 3D, non è un archivio STL, non è una directory di stampatori e non è un portale generico di ricambi. Re-born è il sistema che guida una persona dal problema reale — “questo oggetto non funziona più” — fino alla soluzione migliore: riparazione, ricambio esistente, modello CAD, generazione AI, stampa locale o intervento di un provider.

La piattaforma combina:

- riconoscimento AI di prodotti, componenti e guasti;
- Knowledge Graph dei prodotti, dei pezzi, dei materiali, dei modelli e delle riparazioni;
- marketplace CAD e ricambi;
- rete distribuita di provider di stampa 3D, service professionali e maker qualificati;
- wallet, crediti, royalty, bounty e sistemi di reputazione;
- apprendimento continuo da ogni riparazione completata.

Il principio guida è semplice:

> L’utente non cerca uno STL. L’utente vuole che il suo oggetto torni a funzionare.

---

## 2. Visione

Diventare il riferimento mondiale della Repair Intelligence: il livello intelligente che connette persone, oggetti rotti, conoscenza tecnica, AI, ricambi, maker e manifattura distribuita.

In futuro Re-born dovrà poter rispondere a domande come:

- Che oggetto è questo?
- Quale componente è rotto?
- Esiste già un ricambio compatibile?
- Esiste un modello CAD verificato?
- Serve generarlo tramite AI?
- Quale materiale è più adatto?
- Chi può produrlo vicino a me?
- Quanto costa?
- Qual è la probabilità che la riparazione funzioni?
- Cosa abbiamo imparato da riparazioni simili?

---

## 3. Posizionamento

Re-born NON deve essere percepito come:

- Thingiverse;
- Printables;
- MakerWorld;
- Hubs;
- un clone di Reaplace;
- un preventivatore di stampa 3D;
- un e-commerce di ricambi.

Re-born deve essere percepito come:

- il sistema operativo della riparazione;
- un assistente intelligente per riportare in vita oggetti;
- una piattaforma di conoscenza, produzione e apprendimento;
- un’infrastruttura internazionale per l’economia circolare.

---

## 4. North Star

La metrica guida è:

**Objects Successfully Repaired**

Una riparazione è considerata completata quando:

1. l’oggetto o componente è stato identificato;
2. è stato proposto un percorso di riparazione;
3. l’utente ha ottenuto un ricambio, modello, stampa o supporto;
4. l’utente o il provider conferma l’esito;
5. il Knowledge Graph viene aggiornato.

Metriche secondarie:

- repair attempts started;
- repair completion rate;
- objects saved from waste;
- verified models uploaded;
- provider fulfilment success rate;
- average time to repair;
- AI recognition accuracy;
- CAD model fit success;
- repeat provider usage;
- marketplace liquidity;
- enterprise adoption.

---

## 5. Repair Intelligence Engine™

Il cuore di Re-born è il **Repair Intelligence Engine™**.

È composto da cinque motori:

### 5.1 Recognition Engine

Riconosce oggetti, categorie, componenti, guasti e segnali visivi da immagini, testo e dati caricati dall’utente.

Input:

- foto dell’oggetto;
- foto del pezzo rotto;
- descrizione testuale;
- marca, modello, misure;
- file CAD o STL;
- storico di riparazioni simili.

Output:

- categoria prodotto;
- prodotto candidato;
- componente candidato;
- confidenza;
- dati mancanti;
- percorso successivo consigliato.

### 5.2 Knowledge Engine

Interroga il Knowledge Graph per trovare connessioni tra:

- prodotto;
- componente;
- materiali;
- ricambi;
- modelli CAD;
- provider;
- casi di riparazione;
- errori noti;
- istruzioni;
- compatibilità.

### 5.3 Decision Engine

Sceglie il miglior percorso di riparazione tra:

- ricambio originale;
- ricambio compatibile;
- modello CAD esistente;
- modello generato AI;
- richiesta a maker/community;
- bounty;
- provider locale;
- riparazione manuale;
- non riparabilità motivata.

### 5.4 Learning Engine

Aggiorna il sistema dopo ogni interazione utile:

- conferme utente;
- errori di riconoscimento;
- misure corrette;
- modelli validati;
- recensioni provider;
- failure reports;
- materiali più efficaci;
- correzioni della community.

### 5.5 Trust Engine

Determina affidabilità e ranking di:

- modelli CAD;
- maker;
- provider;
- ricambi;
- istruzioni;
- riparazioni;
- suggerimenti AI.

Il Trust Engine non deve essere solo reputazionale: deve combinare qualità tecnica, esiti reali, puntualità, coerenza, dispute, revisioni e prove d’uso.

---

## 6. Repair Journey Framework™

Il percorso standard è:

1. **Start Repair** — l’utente parte dal problema, non dal file.
2. **Identify Object** — AI e input guidato identificano prodotto/componente.
3. **Diagnose Need** — viene chiarito cosa serve: ricambio, modello, istruzione, provider.
4. **Find or Generate Solution** — Re-born cerca o genera il percorso migliore.
5. **Produce or Obtain** — ricambio, download, stampa, spedizione, ritiro o provider.
6. **Repair Execution** — istruzioni, supporto, tracking, conferme.
7. **Verify Outcome** — esito, foto, feedback, qualità.
8. **Learn** — aggiornamento Knowledge Graph e Trust Engine.

---

## 7. Bounded Context DDD

I bounded context iniziali sono:

1. Identity Domain;
2. Repair Domain;
3. AI Domain;
4. Marketplace Domain;
5. Provider Domain;
6. Knowledge Domain;
7. Wallet Domain;
8. Company Domain.

Ogni bounded context deve avere:

- modello di dominio proprio;
- repository dedicati;
- servizi applicativi;
- eventi di dominio;
- contratti API espliciti;
- test funzionali.

---

## 8. Principi UX

1. La piattaforma parla di riparazione, non di stampa 3D.
2. Ogni schermata deve risolvere un problema reale.
3. Ogni step deve ridurre incertezza.
4. I termini tecnici compaiono solo quando servono.
5. L’utente deve sempre sapere: cosa è stato capito, cosa manca, cosa succede dopo.
6. La AI non deve fingere certezza: deve mostrare livello di confidenza e richiesta di verifica.
7. Marketplace, provider, wallet e download devono essere subordinati al percorso di riparazione.
8. Ogni interazione utile deve alimentare il Knowledge Graph.

---

## 9. Business Model

Flussi economici previsti:

- commissione su stampa distribuita;
- marketplace CAD;
- royalty automatiche ai maker;
- AI Premium;
- Maker PRO;
- Provider PRO;
- Enterprise Portal;
- White Label;
- API Economy;
- marketplace materiali;
- marketplace componenti;
- wallet e Repair Credits;
- bounty system;
- certificazioni e badge professionali;
- dati aggregati e insight enterprise, nel rispetto di privacy e compliance.

Ogni monetizzazione deve aumentare almeno uno di questi asset:

- Knowledge Graph;
- AI Learning;
- community;
- marketplace liquidity;
- enterprise value;
- sustainability impact;
- objects saved.

---

## 10. Vincoli tecnici

Stack iniziale:

- PHP 8.3+;
- HTML5;
- CSS3;
- Vanilla JavaScript;
- SQLite in sviluppo;
- MariaDB/MySQL in produzione;
- Clean Architecture;
- SOLID;
- Repository Pattern;
- Service Layer;
- Domain Events;
- no framework frontend nella prima fase.

Vincoli architetturali:

- separare dominio, applicazione, infrastruttura e interfacce;
- non accoppiare il database alla logica di business;
- evitare codice procedurale non isolato;
- non inserire logica critica nel frontend;
- preparare da subito i confini per API, provider esterni, AI e wallet.

---

## 11. Linguaggio visivo

Riferimenti:

- Apple;
- Linear;
- Arc Browser;
- Nothing;
- Autodesk;
- Figma.

Da evitare:

- Bootstrap;
- template acquistati;
- gradient viola generici;
- glassmorphism pesante;
- estetica crypto/NFT;
- marketplace affollato da e-commerce.

Palette:

- Graphite;
- Off White;
- Repair Green;
- Electric Blue;
- Safety Orange.

Border radius massimo: **4 px**.

---

## 12. Regola per gli agenti futuri

Ogni agente che lavora su Re-born deve comportarsi come:

- Chief Product Officer;
- Chief Software Architect;
- Lead UX/UI Designer;
- operatore di startup SaaS internazionale.

Ogni decisione deve essere valutata rispetto a questi criteri:

1. aumenta la capacità di riparare oggetti?
2. alimenta il Knowledge Graph?
3. migliora la fiducia del sistema?
4. rende il prodotto più scalabile?
5. evita di trasformare Re-born in un semplice marketplace?
6. rispetta la missione “Allow anyone to repair anything”?

---

## 13. Ordine di sviluppo

L’ordine corretto è:

1. Product Book;
2. PRD;
3. UX Bible;
4. Design System;
5. Wireframe;
6. Mockup UI;
7. Prototype;
8. Backend;
9. Frontend;
10. integrazioni AI, wallet e provider.

Non iniziare dal codice prima che Product Book, PRD e UX Bible siano abbastanza chiari.


---

## Execution Doctrine

Re-born must not become a repository-first product. The first MVP must prove that a repair problem can become structured intelligence.

The development order is:

1. capture the repair case;
2. structure the Repair DNA;
3. route the case to a useful path;
4. collect outcome feedback;
5. update the Knowledge Graph;
6. only then optimize marketplace liquidity, credits and automation.

Every feature must be judged against this question:

> Does this help a broken object become repaired, or does it strengthen the intelligence needed to repair the next object?

If the answer is no, it is not MVP-critical.
