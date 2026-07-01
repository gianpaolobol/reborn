param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Ok([string]$Message) {
    Write-Host $Message -ForegroundColor Green
}

function Fail([string]$Message) {
    throw $Message
}

function Assert-Contains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -notlike "*$Needle*") {
        Fail "Missing Maker Faire demo marker: $Label"
    }

    Ok "Maker Faire demo marker present: $Label"
}

function Assert-NotContains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -like "*$Needle*") {
        Fail "Unexpected Maker Faire demo marker: $Label"
    }

    Ok "Maker Faire demo marker absent: $Label"
}

Write-Host "Checking Re-born Maker Faire single-page demo at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15

if (-not $health.success -or $health.status -ne "ok") {
    Fail "Health check failed."
}

Ok "Health: ok"

$index = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/index.html?v=smoke-static-hero" -UseBasicParsing -TimeoutSec 15

if ($index.StatusCode -ne 200) {
    Fail "Prototype index did not return HTTP 200."
}

$indexText = [string]$index.Content

foreach ($marker in @(
    'makerfaire-hero-static',
    'Demo Maker Faire',
    'Hai un pezzo rotto?',
    'Carica una foto. Re-born lo riconosce e ti guida verso il ricambio.',
    'Carica foto e identifica il pezzo',
    'repairFileInput',
    'Foto | Analisi | Ricambio'
)) {
    Assert-Contains $indexText $marker $marker
}

foreach ($marker in @(
    '-a Pronto',
    '-a Modalit demo',
    '-a Modalita demo',
    'Ripara il mio oggetto',
    'Come funziona',
    '>Demo<',
    'Dietro le quinte',
    'Foto caricate',
    'Nessuna foto caricata',
    'Provider',
    'Governance',
    'Readiness',
    'job_id',
    'confidence'
)) {
    Assert-NotContains $indexText $marker $marker
}

$css = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/css/reborn.css?v=smoke-static-hero" -UseBasicParsing -TimeoutSec 15
$cssText = [string]$css.Content

Assert-Contains $cssText 'makerfaire-hero-static' 'static hero CSS'
Assert-Contains $cssText 'Segoe UI' 'safe system font'

$secretPattern = '(^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}'
$scanFiles = @(
    'public/prototype/index.html',
    'public/prototype/assets/js/app.js',
    'public/prototype/assets/js/api-client.js',
    'public/prototype/assets/css/reborn.css',
    'scripts/smoke-makerfaire-user-wizard.ps1',
    '.env.example',
    '.env.ci.example'
)

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

foreach ($relativePath in $scanFiles) {
    $localPath = Join-Path $root ($relativePath -replace '/', [System.IO.Path]::DirectorySeparatorChar)

    if (Test-Path $localPath) {
        $text = Get-Content -Path $localPath -Raw -ErrorAction SilentlyContinue

        if ($text -match $secretPattern) {
            Fail "Potential OpenAI API key found in $relativePath."
        }
    }
}

Ok "Secret scan: no sk- API key pattern found"

Write-Host "Maker Faire single-page demo smoke test passed." -ForegroundColor Green
