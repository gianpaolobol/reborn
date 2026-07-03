param(
  [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Fail([string]$Message) {
  throw $Message
}

function Ok([string]$Message) {
  Write-Host "OK: $Message"
}

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if ($health.status -ne "ok") {
  Fail "/api/health did not return ok"
}
Ok "/api/health ok"

$rootPage = Invoke-WebRequest -Method GET -Uri "$BaseUrl/" -TimeoutSec 15
if ($rootPage.StatusCode -ne 200) {
  Fail "root product UI did not return HTTP 200"
}
Ok "root product UI 200"

$page = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/index.html" -TimeoutSec 15
if ($page.StatusCode -ne 200) {
  Fail "prototype index did not return HTTP 200"
}
Ok "prototype index 200"

$content = [string]$page.Content
$appJs = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -TimeoutSec 15
$css = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/css/reborn.css" -TimeoutSec 15
$rootContent = [string]$rootPage.Content
$source = $rootContent + "`n" + $content + "`n" + [string]$appJs.Content + "`n" + [string]$css.Content
$requiredMarkers = @(
  "minimal-live-ui",
  "serverStatus",
  "imageUploadInput",
  "uploadButton",
  "Carica foto e riconosci",
  "Analisi in corso",
  "linear-gradient(90deg",
  "associatedImagesHtml",
  "associated-images",
  "recognitionResult",
  "criteria-panel",
  "Criteri di ricerca",
  "Identificazione",
  "Richiesta di identificazione assistita",
  "Carica il modello 3D corrispondente alla tua ricerca",
  "Link al modello 3D<input",
  "File modello 3D<input",
  "data-action=\"save-library\"",
  "recognized-part-name",
  "confidence-chip",
  "Marca/Codice",
  "Dimensioni",
  "Scegli i criteri di ricerca per trovare il tuo ricambio",
  "Ricerca composta",
  "composedQueryInput",
  "criteriaSearchQuery(result)",
  "updateMarketplaceLinkTargets",
  "data-marketplace-source",
  "ti aiuteremo a trovare il tuo ricambio",
  "Email<input",
  "Nome<input",
  "significantWords(value, 3"
)

foreach ($marker in $requiredMarkers) {
  if ($source -notlike "*$marker*") {
    Fail "Missing minimal UI marker: $marker"
  }
}
Ok "minimal UI markers present"

$forbiddenMarkers = @(
  "topnav",
  "navbar",
  "Maker Faire",
  "Ripara il mio oggetto",
  "Come funziona",
  "Console avanzate",
  "governance",
  "readiness",
  "job_id",
  "Controllare .env",
  "Verifica avanzata non completata",
  "slicer configurato",
  "Richiedi stampa",
  "Apri migliore ricerca",
  "Ricerche generate",
  "STL 3MF ricambio stampa 3D",
  "Scegli quali informazioni usare",
  "La ricerca composta resta pulita",
  "Per procedere con la riproduzione",
  "Invia a Re-born una richiesta",
  "Email per risposta",
  "Nome facoltativo",
  "significantWords(value, 4",
  "Fonte del modello",
  "Ricerca usata",
  "Link al modello 3D opzionale",
  "Il file viene salvato nella libreria Re-born"
)

foreach ($marker in $forbiddenMarkers) {
  if ($source -like "*$marker*") {
    Fail "Forbidden visible marker found in UI source: $marker"
  }
}
Ok "forbidden index markers absent"

$publicFiles = Get-ChildItem -Path ".\public" -File -Recurse -Include *.html,*.js,*.css,*.php,*.json -ErrorAction SilentlyContinue
foreach ($file in $publicFiles) {
  $text = Get-Content -LiteralPath $file.FullName -Raw -ErrorAction SilentlyContinue
  if ($text -match "sk-[A-Za-z0-9]") {
    Fail "Potential API key found in public file: $($file.FullName)"
  }
}
Ok "no sk- API keys in public files"

Write-Host "Minimal live UI smoke passed."
