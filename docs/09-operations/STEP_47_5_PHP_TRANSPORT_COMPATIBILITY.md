# STEP 47.5 — Live OpenAI Transport Compatibility Hotfix

Questa hotfix risolve il caso Windows/PHP locale in cui la configurazione OpenAI risulta attiva ma la chiamata Vision fallisce prima di raggiungere l'API.

Problemi corretti:

- PHP senza estensione `curl` caricata.
- PHP senza wrapper `https` perché `openssl` non è abilitato.
- Warning HTML emessi da `file_get_contents()` che corrompevano la risposta JSON HTTP.
- Assenza di `mbstring`, con errore `Call to undefined function ... mb_substr()`.

Il gateway ora prova, in ordine:

1. estensione PHP cURL;
2. stream HTTPS PHP solo se il wrapper `https` è disponibile;
3. fallback a `curl.exe`/`curl` di sistema tramite file temporanei.

Se nessun trasporto HTTPS è disponibile, restituisce un errore diagnostico leggibile invece di sporcare la risposta con warning PHP.

Validazione live consigliata:

```powershell
cd C:\REBORN\REBORN
powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-vision-quality-live.ps1 `
  -BaseUrl http://127.0.0.1:8080 `
  -ImagePath "C:\REBORN\reborn\test-images\165314-dishwasher-wheel.jpg.jpg"
```

Risultato atteso:

- `recognition_mode: openai_vision_api` oppure `openai_vision_api_quality_retry`;
- testo riconosciuto: `165314`, `Dishwasher Lower Rack Wheel`;
- output utente: `Ruota del cestello inferiore per lavastoviglie`.
