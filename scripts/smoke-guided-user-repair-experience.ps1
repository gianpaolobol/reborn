param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function Assert-Contains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -notlike "*$Needle*") { Fail "Missing expected UX marker: $Label" }
    Ok "UX marker present: $Label"
}
function Assert-NotContains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -like "*$Needle*") { Fail "Unexpected old UX marker still visible: $Label" }
    Ok "Old UX marker absent: $Label"
}

Write-Host "Checking Re-born Step 43 Guided User Repair Experience at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
Ok "Health: ok"

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
    "imageUploadInput",
    "uploadButton",
    "Carica foto e riconosci",
    "Analisi in corso",
    "recognitionResult",
    "associatedImagesHtml",
    "libraryMatchesHtml",
    "searchOtherModelsButtonHtml",
    "Cerca altri modelli",
    "criteriaSearchQuery(result)",
    "data-action=\"save-library\""
)) {
    Assert-Contains $source $marker $marker
}

foreach ($marker in @(
    "topnav",
    "navbar",
    "Repair my object",
    "Ripara il mio oggetto",
    "Advanced consoles",
    "Console avanzate",
    "AI Gov",
    "Geometry</a>",
    "Marketplace revenue"
)) {
    Assert-NotContains $source $marker $marker
}

$loginBody = @{ email = "repair.user@reborn.local"; password = "password" } | ConvertTo-Json -Compress
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody -TimeoutSec 15
if (-not $login.success -or -not $login.token.access_token) { Fail "Repair user login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Repair user login: ok"

$caseBody = @{
    title = "Step 43 compact guided UX smoke case"
    description = "Created by the guided user repair experience smoke test to verify that the compact recognition UI still connects to the real repair-case API."
    category = "home_appliance"
} | ConvertTo-Json -Compress
$case = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -Headers $headers -ContentType "application/json" -Body $caseBody -TimeoutSec 15
if (-not $case.success -or -not $case.repair_case.id) { Fail "Guided repair case creation failed." }
Ok "Guided compact repair UI can create a real case: ok"

Write-Host "Step 43 guided user repair experience smoke test passed." -ForegroundColor Green
