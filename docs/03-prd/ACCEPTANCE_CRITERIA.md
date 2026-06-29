# PRD — Acceptance Criteria

## Repair Case Creation

Un Repair Case è accettato quando:

- l'utente può descrivere l'oggetto;
- l'utente può caricare almeno una immagine;
- il sistema genera un ID caso;
- il caso ha stato iniziale `draft` o `submitted`;
- il caso è collegato all'utente;
- eventuali errori upload sono chiari e recuperabili.

## AI Recognition

Il riconoscimento è accettato quando:

- produce almeno un'ipotesi prodotto/componente o uno stato `unknown`;
- mostra un confidence score;
- permette correzione manuale;
- registra input, output e versione del motore;
- non blocca il flusso se la confidenza è bassa.

## Repair Path Recommendation

Un repair path è accettato quando mostra:

- opzione consigliata;
- alternative;
- costo stimato;
- tempo stimato;
- probabilità di successo;
- motivazione della raccomandazione;
- prossima azione chiara.

## Maker Model Upload

Un modello maker è accettato quando:

- ha file valido;
- ha licenza/diritti dichiarati;
- ha compatibilità dichiarata;
- ha materiale consigliato;
- ha versioning;
- può generare royalty.

## Provider Quote

Un preventivo provider è accettato quando include:

- prezzo;
- materiale;
- tecnologia;
- tempo produzione;
- opzioni consegna/ritiro;
- fee piattaforma;
- eventuale royalty maker.

## Repair Validation

Una riparazione è validata quando:

- l'utente conferma esito;
- indica se il pezzo è installabile;
- può caricare foto finale;
- lascia feedback;
- il sistema aggiorna Repair Score e Knowledge Graph.
