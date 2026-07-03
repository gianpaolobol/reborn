param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function Assert-Contains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -notlike "*$Needle*") { Fail "Missing expected Step 44 marker: $Label" }
    Ok "Step 44 marker present: $Label"
}
function Assert-NotContains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -like "*$Needle*") { Fail "Unexpected confusing marker still visible: $Label" }
    Ok "Confusing marker absent: $Label"
}

Write-Host "Checking Re-born Step 44 Repair-First Offer Architecture at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
Ok "Health: ok"

$capabilities = @($health.capabilities)
foreach ($capability in @("fixpart_benchmark_positioning", "repair_first_offer_architecture", "replacement_part_generation_wizard", "four_step_user_repair_flow")) {
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
    "Carica foto e riconosci",
    "Analisi in corso",
    "recognized-part-name",
    "confidence-chip",
    "Modelli 3D già disponibili",
    "libraryMatchesHtml",
    "model-viewer",
    "downloads",
    "preference-button",
    "Cerca altri modelli",
    "secondary-search-instance",
    "Ricerca composta"
)) {
    Assert-Contains $source $marker $marker
}

foreach ($marker in @(
    "Repair my object",
    "Ripara il mio oggetto",
    "Advanced consoles",
    "Console avanzate",
    "AI Gov",
    "Geometry</a>",
    "Five clear steps"
)) {
    Assert-NotContains $source $marker $marker
}

$loginBody = @{ email = "repair.user@reborn.local"; password = "password" } | ConvertTo-Json -Compress
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody -TimeoutSec 15
if (-not $login.success -or -not $login.token.access_token) { Fail "Repair user login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Repair user login: ok"

$caseBody = @{
    title = "Step 44 compact model finder smoke case"
    description = "Created by the repair-first offer architecture smoke test to verify the compact image-recognition and model-library flow still creates a real repair request."
    category = "home_appliance"
} | ConvertTo-Json -Compress
$case = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -Headers $headers -ContentType "application/json" -Body $caseBody -TimeoutSec 15
if (-not $case.success -or -not $case.repair_case.id) { Fail "Repair-first case creation failed." }
Ok "Repair-first compact flow can create a real case: ok"

Write-Host "Step 44 repair-first offer architecture smoke test passed." -ForegroundColor Green
