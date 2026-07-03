# HANDOFF COMPLETO - RE-BORN / Minimal Live UI su demo predefinita

**Data:** 2026-07-02  
**Progetto:** Re-born - Repair Intelligence Platform  
**Repository locale utente:** `C:\REBORN\REBORN`  
**Baseline codice caricata dall'utente:** `reborn-b9ff8b4ee5a8c18d4ea7cbc4e8ced5cc07f3b99d.zip`  
**Pacchetto modifica preparato:** `reborn-minimal-live-ui-files.zip` + `reborn-minimal-live-ui.patch`  
**Obiettivo per il prossimo agente:** stabilizzare la demo predefinita del prototipo come interfaccia minimale, live, con riconoscimento immagini realmente collegato alle API backend/provider AI.

---

## 1. Obiettivo assoluto

Il prossimo agente deve continuare dallo stato attuale del progetto **senza creare una nuova demo Maker Faire separata**.

La richiesta finale dell'utente e stata chiara:

> Ripartire dalla versione funzionante del codice con server e API funzionanti, ma semplificare l'interfaccia grafica all'essenziale. Non creare una nuova demo Maker Faire. Continuare con la demo predefinita nello ZIP caricato.

La UI deve mostrare solo:

```text
1. Connessione al server
2. Caricamento immagini
3. Barra di caricamento
4. Preview piccole delle immagini caricate
5. Risposta del riconoscimento immagini
```

Tutto il resto deve essere rimosso o nascosto.

---

## 2. Stato attuale del lavoro

E stata prodotta una patch minimal live UI che modifica solo:

```text
public/prototype/index.html
public/prototype/assets/js/app.js
public/prototype/assets/css/reborn.css
```

Sono stati generati:

```text
reborn-minimal-live-ui-files.zip
reborn-minimal-live-ui.patch
```

In questo handoff package e incluso anche uno ZIP completo del progetto, costruito partendo dalla baseline caricata dall'utente e sovrascrivendo i tre file del prototipo con la versione minimal live UI.

Nota importante: il codice del progetto contiene gia backend, autenticazione demo, upload immagini e gateway AI. La modifica UI deve agganciarsi a questi flussi esistenti, non sostituirli con chiamate frontend dirette ai provider AI.

---

## 3. Decisione prodotto gia presa

La demo non deve piu essere una pagina narrativa o commerciale.

### Da eliminare dalla UI visibile

```text
navbar
footer promozionale
Ripara il mio oggetto
Come funziona
Demo
Maker Faire
Console avanzate
Account
Aiuto
Provider
Governance
Readiness
Audit log
Investor
Pilot
Wizard a step
Dietro le quinte
Foto caricate / Nessuna foto caricata come blocchi narrativi
Parti da una sola foto
Legge testo, codici e misure visibili
Capisce quali viste o misure mancano
Prepara la strada piu rapida
Modalita demo
-a Pronto
-a Modalit demo
confidence
job_id
fallback
completed
provider routing
```

### Da mantenere nella UI visibile

```text
Server in collegamento / Server Pronto
Bottone upload immagini
Progress bar
Preview piccole immagini
Pannello risposta riconoscimento
```

---

## 4. Architettura corretta del flusso live

La demo deve essere realmente collegata alle API di riconoscimento immagini.

Flusso corretto:

```text
Browser
-> Backend PHP Re-born
-> Provider AI configurato nel backend
-> Risposta JSON
-> UI minimal live
```

Flusso vietato:

```text
Browser
-> OpenAI/Gemini/xAI direttamente
```

Le chiavi API non devono mai comparire nel frontend, in `index.html`, in `app.js`, nel repository o negli smoke test.

---

## 5. Variabili `.env` da verificare

Nel file locale:

```text
C:\REBORN\REBORN\.env
```

servono valori compatibili con il provider live configurato.

Esempio OpenAI:

```env
APP_ENV=development

AI_PHOTO_RECOGNITION_ENABLED=true
AI_PHOTO_RECOGNITION_PROVIDER=openai
OPENAI_API_KEY=sk-CHIAVE_REALE_SOLO_LOCALE
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT_SECONDS=90
OPENAI_VISION_MAX_IMAGES=3
OPENAI_VISION_MAX_IMAGE_BYTES=5242880
OPENAI_VISION_MODEL=gpt-4.1-mini
```

Se il progetto usa router multi-provider, controllare anche le variabili relative a Gemini o altri provider gia presenti. Non aggiungere Grok/xAI o altri provider nuovi senza richiesta esplicita.

### Sicurezza

Prima del commit:

```powershell
git ls-files .env
git diff --cached | Select-String "sk-"
```

Risultato corretto: nessun output.

---

## 6. File chiave e responsabilita

### `public/prototype/index.html`

Deve essere la demo predefinita del prototipo.

Deve contenere solo lo shell essenziale:

```text
root app
server status
upload input
progress bar
preview container
result container
script JS/CSS corretti
```

Non deve contenere navbar, footer, menu o testi da presentazione.

---

### `public/prototype/assets/js/app.js`

Deve gestire:

```text
health check server
sessione demo utente repair
upload immagini
progress bar
preview immagini
chiamata recognition jobs
render del risultato AI
stati di errore chiari
```

Funzioni del progetto da preservare o riusare se presenti:

```text
ensureLiveApiForWizardRecognition()
ensureWizardRepairUserSession()
uploadSelectedRepairFilesInternal()
requestAIRecognitionForAttachments()
```

Se il prossimo agente trova che alcune funzioni sono troppo stratificate/fragili, puo creare un piccolo adapter minimal, ma deve comunque passare dal backend esistente.

---

### `public/prototype/assets/css/reborn.css`

Deve contenere solo stile utile a:

```text
layout minimale
server status pill
upload button
progress bar
image preview grid
recognition result card
responsive mobile
```

Usare font di sistema:

```css
font-family: "Segoe UI", Arial, Helvetica, sans-serif;
```

Evitare font custom, caratteri speciali, frecce tipografiche e simboli Unicode non necessari.

---

## 7. Stati UI richiesti

### Stato iniziale

```text
Server in collegamento
```

### Server disponibile

```text
Server Pronto
```

### Dopo selezione immagini

```text
preview immagini piccole
barra di caricamento
Caricamento immagini
Analisi in corso
```

### Risposta riconosciuta

```text
Immagine riconosciuta
Pezzo: ...
Funzione: ...
Codice letto: ...
Misure lette: ...
Prossimo passo: ...
```

### Risposta non sufficiente

```text
Servono altre immagini
Carica una foto laterale, del pezzo montato o con una misura visibile.
```

### Errore provider/API

```text
Riconoscimento live non disponibile
Il server risponde, ma il provider AI non ha restituito un risultato.
Controllare .env, API key e modello configurato.
```

Non presentare mai un fallback/mock come se fosse riconoscimento reale.

---

## 8. Comandi per avviare il progetto

PowerShell 1:

```powershell
cd C:\REBORN\REBORN

Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force

$env:APP_ENV="development"

php scripts/setup-dev.php
php scripts/reset-demo-credentials.php
php scripts/verify-demo-credentials.php

php -S 127.0.0.1:8080 -t public public/index.php
```

Lasciare aperta questa finestra.

Browser:

```text
http://127.0.0.1:8080/prototype/index.html?v=minimal-live-ui
```

Hard refresh:

```text
CTRL + F5
```

---

## 9. Verifiche tecniche

### Sintassi JS

```powershell
cd C:\REBORN\REBORN

node --check .\public\prototype\assets\js\app.js
node --check .\public\prototype\assets\js\api-client.js
```

### Health server

```powershell
Invoke-RestMethod -Method GET -Uri "http://127.0.0.1:8080/api/health"
```

Atteso:

```text
status: ok
```

### Login demo manuale

```powershell
$body = @{
  email = "repair.user@reborn.local"
  password = "password"
} | ConvertTo-Json -Compress

Invoke-RestMethod `
  -Method POST `
  -Uri "http://127.0.0.1:8080/api/v1/auth/login" `
  -ContentType "application/json" `
  -Body $body `
  -TimeoutSec 20 | ConvertTo-Json -Depth 10
```

---

## 10. Test riconoscimento live

Prima del browser, testare il provider live da script:

```powershell
cd C:\REBORN\REBORN

powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-photo-recognition-live.ps1 -BaseUrl http://127.0.0.1:8080 -ImagePath "C:\PERCORSO\ALLA\FOTO.jpg"
```

Risultato accettabile:

```text
Recognition mode: openai_vision_api
Identification status: recognized
```

Oppure se router/provider diverso:

```text
Recognition mode: gemini_vision_api
Identification status: recognized
```

Risultato non accettabile:

```text
fallback
mock
deterministic
openai error
provider unavailable
```

---

## 11. Smoke test consigliato per UI minimal

Creare o aggiornare:

```text
scripts/smoke-minimal-live-ui.ps1
```

Lo smoke deve verificare solo:

```text
/api/health ok
index.html 200
server status presente
input file presente
progress bar presente
preview grid presente
recognition result panel presente
assenza navbar/testi avanzati
assenza sk- nei file pubblici
```

Marker consigliati:

```text
minimal-live-ui
serverStatus
imageUploadInput
uploadProgress
previewGrid
recognitionResult
```

Non deve cercare marker vecchi:

```text
Ripara il mio oggetto
Come funziona
Demo
Maker Faire
Console avanzate
```

---

## 12. Problemi gia incontrati e da non ripetere

### Encoding / mojibake

Sono comparsi testi rotti:

```text
Ã¢
Ãƒ
Â
ciÃƒ
puÃƒ
-a
```

Scrivere file da PowerShell usando:

```powershell
[System.IO.File]::WriteAllText(
  (Resolve-Path $path),
  $content,
  [System.Text.UTF8Encoding]::new($false)
)
```

Evitare:

```text
freccia unicode
apostrofi tipografici
virgolette tipografiche
trattini lunghi
spazi non separabili
```

### Apostrofi in JS

Errore gia avuto:

```text
SyntaxError: Unexpected identifier 'area'
```

Causa:

```js
advancedConsoleNote: 'Quest'area e separata...'
```

Corretto:

```js
advancedConsoleNote: 'Quest\'area e separata...'
```

Meglio ancora: evitare apostrofi nelle stringhe JS.

### Hero statica

Era stata usata una hero statica per recuperare la pagina quando il render JS era rotto. Ora non deve diventare la soluzione finale. La UI deve essere minimale ma live, non solo decorativa.

---

## 13. Git e commit

Prima del commit:

```powershell
git status
```

Non committare:

```text
.env
storage/logs/*
storage/database/*
zip temporanei
patch temporanee non richieste
```

Pulizia log:

```powershell
git restore .\storage\logs\ci-smoke-results.json 2>$null
git restore .\storage\logs\ci-failure-diagnostics.json 2>$null
Remove-Item .\storage\logs\ci-smoke-auth-guard-failure.json -ErrorAction SilentlyContinue
```

Aggiungere solo file necessari:

```powershell
git add .\public\prototype\index.html
git add .\public\prototype\assets\js\app.js
git add .\public\prototype\assets\css\reborn.css
```

Se creato smoke minimal:

```powershell
git add .\scripts\smoke-minimal-live-ui.ps1
```

Commit consigliato:

```powershell
git commit -m "Simplify prototype to minimal live recognition UI"
```

---

## 14. Acceptance criteria

La modifica e accettata se:

```text
1. La pagina predefinita del prototipo si apre senza route speciali.
2. Non c'e navbar.
3. Non ci sono testi promozionali o tecnici.
4. Si vede stato connessione server.
5. Si possono caricare immagini.
6. Si vedono preview piccole delle immagini caricate.
7. Si vede una barra di caricamento.
8. La risposta del riconoscimento appare nella stessa pagina.
9. Il riconoscimento passa dal backend e usa provider AI live configurato.
10. Se il provider AI non risponde, la UI lo dice chiaramente.
```

Non e accettata se:

```text
1. Appare una nuova demo Maker Faire separata.
2. Serve navigare in piu pagine.
3. L'utente vede console, provider, governance, readiness o job_id.
4. La risposta e mock/fallback ma viene presentata come riconoscimento reale.
5. La chiave API finisce in frontend o repository.
6. La pagina e solo una hero statica senza chiamata live.
```

---

## 15. Prossimo step consigliato

### Step successivo - Minimal Live Recognition UI Stabilization

Azioni prioritarie:

```text
1. Applicare/verificare la patch reborn-minimal-live-ui sulla baseline caricata.
2. Testare debug-ai-photo-recognition-live.ps1 con una foto reale.
3. Agganciare la UI minimal al risultato reale del backend.
4. Creare smoke-minimal-live-ui.ps1 non legato ai vecchi marker.
5. Fare commit pulito senza log e senza .env.
```

Regola guida:

```text
Non aggiungere nuove pagine.
Non creare nuova demo.
Non aggiungere provider.
Non mostrare opzioni tecniche.
Rendere la demo predefinita essenziale, live e affidabile.
```
