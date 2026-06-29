# UX Bible — UX States

## Principio

Re-born deve ridurre incertezza. Ogni stato deve spiegare cosa sta succedendo e qual è il prossimo passo.

## Stati obbligatori

### Default

La schermata mostra il percorso principale senza ambiguità.

### Loading

Mostrare avanzamento e aspettativa, non spinner generici senza contesto.

Esempio: “Sto confrontando il componente con casi di riparazione simili”.

### Empty

Spiegare perché non c'è contenuto e come crearlo.

### Error

Errore recuperabile, con testo umano e azione successiva.

### Partial confidence

Quando l'AI non è sicura, mostrare:

- ipotesi;
- confidence score;
- motivo dell'incertezza;
- azione per migliorare il risultato.

### Blocked

Quando il flusso non può procedere, spiegare:

- cosa manca;
- chi può sbloccarlo;
- quanto tempo potrebbe richiedere;
- alternativa disponibile.

### Success

Il successo non è “ordine completato”. Il vero successo è “oggetto riparato”.
