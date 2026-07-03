param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function Assert-Contains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -notlike "*$Needle*") { Fail "Missing Step 46 marker: $Label" }
    Ok "Step 46 marker present: $Label"
}
function Assert-NotContains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -like "*$Needle*") { Fail "Unexpected Step 46 visible marker: $Label" }
    Ok "Step 46 marker absent: $Label"
}

Write-Host "Checking Re-born Step 46 User Repair Wizard Simplification at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
Ok "Health: ok"

$capabilities = @($health.capabilities)
foreach ($capability in @("user_repair_wizard_simplification", "decision_behind_the_scenes_wizard", "one_primary_cta_user_flow")) {
    if ($capabilities -notcontains $capability) { Fail "Health capability missing: $capability" }
    Ok "Health capability present: $capability"
}

$index = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/index.html" -UseBasicParsing -TimeoutSec 15
if ($index.StatusCode -ne 200) { Fail "Prototype index did not return HTTP 200." }
$indexText = [string]$index.Content

$app = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -UseBasicParsing -TimeoutSec 15
if ($app.StatusCode -ne 200) { Fail "app.js did not return HTTP 200." }
$appText = [string]$app.Content

$css = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/css/reborn.css" -UseBasicParsing -TimeoutSec 15
if ($css.StatusCode -ne 200) { Fail "reborn.css did not return HTTP 200." }
$cssText = [string]$css.Content

$source = $indexText + "`n" + $appText + "`n" + $cssText

foreach ($marker in @(
    "minimal-live-ui",
    "serverStatus",
    "Carica foto e riconosci",
    "Analisi in corso",
    "linear-gradient(90deg",
    "recognitionResult",
    "Servono altre immagini",
    "recognized-part-name",
    "associatedImagesHtml",
    "libraryMatchesHtml",
    "searchOtherModelsButtonHtml",
    "Cerca altri modelli"
)) {
    Assert-Contains $source $marker $marker
}

foreach ($marker in @(
    "Ripara un pezzo",
    "Le mie richieste",
    "Console avanzate",
    "AI Gov",
    "Geometry</a>",
    "Marketplace revenue",
    "Decisioni in background"
)) {
    Assert-NotContains $source $marker $marker
}

Write-Host "Step 46 user repair wizard simplification smoke test passed." -ForegroundColor Green
