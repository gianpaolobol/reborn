# Re-born minimal live model finder UI

Questa è la UI consumer minimale del prodotto Re-born.

Percorso principale:

```text
/
```

Percorso tecnico diretto ancora disponibile:

```text
/prototype/index.html
```

Flusso utente supportato:

1. stato connessione server;
2. caricamento immagini JPG/PNG/WEBP;
3. barra di caricamento;
4. preview immagini;
5. riconoscimento AI normalizzato dal backend;
6. query STL/3MF generate;
7. candidati modello 3D;
8. azioni finali: apri/scarica modello, richiedi stampa, correggi identificazione, richiesta assistita.

La UI non espone provider AI, API key, `.env`, quality gate o messaggi di debug. Queste informazioni restano nel backend e negli script tecnici.
