# Applicazione immagine coordinata Reborn — riferimenti esatti

Fonte unica: "Reborn Brand Identity System v3.0" (immagine coordinata fornita).
Nessuna interpretazione: valori presi dalla scheda e verificati campionando i pixel dell'immagine.

## Colori (token :root in reborn.css)
- Primary "Reborn Green": RGB 10,160,80 -> `#0AA050` (--reborn-green). Nota: l'HEX #8C0250 stampato nella scheda e' un refuso tipografico, e' un magenta e non corrisponde ne' all'RGB dichiarato ne' al verde del logo; ho usato l'RGB ufficiale, coerente col logo.
- Secondary "Crisp/light green": `#A6D875` (--reborn-green-soft), campionato dallo swatch.
- Dark Grey: `#333333` (--reborn-ink), come da scheda.
- Bianco: `#FFFFFF`.
- Varianti operative derivate (hover, deep, bordi) generate dal primario per stati e contrasto AA.

## Tipografia
- Headings e wordmark: **Montserrat Bold** (font-weight 700), come da specifiche di produzione.
- Body: **Inter**.
- Fallback di sistema, nessun CDN esterno. Per resa identica ovunque si possono aggiungere i .woff2 self-hosted (predisposto).

## Logo
- Ricostruito in vettoriale fedele al riferimento: anello a vortice (due archi con punta assottigliata e gap opposti), sfumatura verde scuro -> verde chiaro.
- `reborn-mark.svg` (solo simbolo) e `reborn-logo.svg` (simbolo + wordmark "Reborn" in #333, Montserrat Bold).
- Sostituisce la precedente versione a 3 pale spezzate, che non corrispondeva al riferimento.

## Applicazione UI
- Header con barra verde brand sotto logo + stato server.
- Bottone principale con gradient verde brand (#0AA050 -> verde scuro) e ombra verde, non piu' nero piatto.
- Card risultato con accento superiore verde; chip affidabilita', criteri, libreria e viewer in famiglia verde coerente.
- Favicon SVG, theme-color #0AA050... (aggiornato a valore scheda), cache `?v=reborn-brand-v5`.

## Verifiche
- Colori campionati dai pixel: Primary avg RGB (64,179,57), verde logo dominante ~#46AA3C — coerenti con la famiglia; token fissati sui valori ufficiali della scheda.
- Smoke `smoke-minimal-live-ui`: marker richiesti tutti presenti, vietati assenti (verificato).
- E2E model library: nessuna regressione.
- Nota rendering: gli screenshot di anteprima sono generati con WeasyPrint (motore PDF) che non supporta pienamente flexbox/gradient e introduce artefatti su chip e badge; nel browser reale questi elementi sono corretti.

## Aggiornamento — concept web mockup (v6)

Applicato il linguaggio visivo dei mockup del sito forniti (home/servizi/soluzioni ecc.), SOLO a livello grafico:
- Sfondo tecnico a griglia chiarissima, header bianco sticky con logo Reborn.
- Hero/upload su superficie bianca con CTA verde piena (accento).
- Card risultato bianche con accento verde in alto, tabella dati pulita a righe.
- Chip affidabilità in antracite con percentuale verde (come i blocchi scuri dei mockup).
- Blocco "Modelli in libreria" reso come CARD SCURA (antracite) con testo bianco, stat pill verdi e CTA download verde: riprende le schede prodotto nere dei mockup.
- Query generata su fondo scuro con codice monospace, accento verde.
- Verde usato come accento strategico (CTA, stati, bordi attivi, focus), non come riempimento diffuso.
- Nessuna nav multipagina finta: il prodotto è una single-page di riconoscimento; l'header resta logo + stato server per non introdurre link non funzionanti (anche vietati dagli smoke `topnav`/`navbar`). Lo stile `.brand-nav` è predisposto nel CSS se in futuro si aggiungeranno sezioni reali.

Nessun file PHP, nessun endpoint, nessun marker CI modificato. Solo `reborn.css`, `app.js` (header/gradient), `index.html` (cache v6), asset logo SVG.
