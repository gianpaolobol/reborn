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

$page = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/index.html" -TimeoutSec 15
if ($page.StatusCode -ne 200) {
  Fail "prototype index did not return HTTP 200"
}
Ok "index.html 200"

$content = [string]$page.Content
$appJs = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -TimeoutSec 15
$css = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/css/reborn.css" -TimeoutSec 15
$source = $content + "`n" + [string]$appJs.Content + "`n" + [string]$css.Content
$requiredMarkers = @(
  "minimal-live-ui",
  "serverStatus",
  "imageUploadInput",
  "uploadProgress",
  "previewGrid",
  "recognitionResult",
  "marketplace-panel"
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
  "job_id"
)

foreach ($marker in $forbiddenMarkers) {
  if ($content -like "*$marker*") {
    Fail "Forbidden visible marker found in index.html: $marker"
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
