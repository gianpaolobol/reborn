# Frontend Architecture — Re-born

## Stack

- HTML5
- CSS3
- Vanilla JavaScript
- no framework frontend nella fase iniziale

## Principi

- componenti modulari;
- design tokens CSS;
- progressive enhancement;
- accessibilità;
- stati UX espliciti;
- nessuna dipendenza pesante non necessaria;
- UI proprietaria, no Bootstrap.

## Struttura proposta

```text
public/
  index.php
  assets/
    css/
      tokens.css
      base.css
      components.css
      pages.css
    js/
      app.js
      api-client.js
      components/
      pages/
    img/
```

## Regola

Il frontend deve seguire il Repair Journey, non la struttura database.
