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
Assert-Contains $indexText "Ripara un pezzo" "simple primary nav"
Assert-Contains $indexText "Le mie richieste" "plain requests nav"
Assert-Contains $indexText "Aiuto" "help nav"
Assert-Contains $indexText "Foto" "photo-analysis-replacement brand"
Assert-Contains $indexText "Console avanzate" "advanced consoles still separated"
Assert-NotContains $indexText "AI Gov" "AI governance not in primary nav"
Assert-NotContains $indexText "Geometry</a>" "geometry not in primary nav"
Assert-NotContains $indexText "Marketplace revenue" "marketplace revenue not in primary nav"

$app = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -UseBasicParsing -TimeoutSec 15
if ($app.StatusCode -ne 200) { Fail "app.js did not return HTTP 200." }
$appText = [string]$app.Content
foreach ($marker in @(
    "STEP 46 - User Repair Wizard Simplification",
    "userRepairWizard",
    "Foto -> Analisi -> Ricambio",
    "Hai un pezzo rotto?",
    "Carica foto del pezzo",
    "Pezzo riconosciuto",
    "Servono altre immagini",
    "Decisioni in background",
    "continueToRecommendedSolution",
    "requestWizardQuote",
    "one_primary_cta_user_flow"
)) {
    if ($appText -notlike "*$marker*") { Fail "Missing Step 46 app marker: $marker" }
    Ok "Step 46 app marker present: $marker"
}
Assert-Contains $appText "'/capture': userRepairWizard" "legacy capture route maps to simplified wizard"
Assert-Contains $appText "'/repair-paths': userRepairWizard" "legacy repair paths route maps to simplified wizard"
Assert-Contains $appText "'/provider-network': userRepairWizard" "legacy provider route maps to simplified wizard"

$css = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/css/reborn.css" -UseBasicParsing -TimeoutSec 15
if ($css.StatusCode -ne 200) { Fail "reborn.css did not return HTTP 200." }
$cssText = [string]$css.Content
foreach ($marker in @(".user-wizard-hero", ".single-primary-action", ".user-repair-progress", ".background-card", ".minimal-fields")) {
    if ($cssText -notlike "*$marker*") { Fail "Missing Step 46 CSS marker: $marker" }
    Ok "Step 46 CSS marker present: $marker"
}

foreach ($marker in @(
    "DEMO_REPAIR_USER",
    "ensureWizardRepairUserSession",
    "Accesso demo automatico",
    "category: 'generic'"
)) {
    if ($appText -notlike "*$marker*") { Fail "Missing Step 49 live demo wizard marker: $marker" }
    Ok "Step 49 live demo wizard marker present: $marker"
}

Write-Host "Step 46/49 user repair wizard simplification smoke test passed." -ForegroundColor Green
