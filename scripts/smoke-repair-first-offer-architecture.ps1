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
function Assert-ContainsAny([string]$Text, [string[]]$Needles, [string]$Label) {
    foreach ($Needle in $Needles) {
        if ($Text -like "*$Needle*") { Ok "Step 44 marker present: $Label"; return }
    }
    Fail "Missing expected Step 44 marker: $Label"
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

Assert-ContainsAny $indexText @("Repair my object", "Ripara il mio oggetto") "plain primary journey entry"
Assert-ContainsAny $indexText @("1. Problem", "1. Problema") "four-step problem nav"
Assert-ContainsAny $indexText @("2. Photos & files", "2. Foto") "four-step evidence nav"
Assert-ContainsAny $indexText @("3. Generate part", "3. Genera ricambio") "replacement generation nav"
Assert-ContainsAny $indexText @("4. Quote", "4. Preventivo") "quote nav"
Assert-ContainsAny $indexText @("Advanced consoles", "Console avanzate") "advanced tools remain grouped"
Assert-NotContains $indexText "AI Gov" "technical AI governance absent from primary nav"
Assert-NotContains $indexText "Geometry</a>" "technical geometry console absent from primary nav"

$app = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -UseBasicParsing -TimeoutSec 15
if ($app.StatusCode -ne 200) { Fail "app.js did not return HTTP 200." }
$appText = [string]$app.Content
Assert-Contains $appText "Generate the missing replacement part" "repair-first hero"
Assert-Contains $appText "Four steps. One main action at a time" "reduced journey copy"
Assert-Contains $appText "replacementRouteCards" "three-outcome offer helper"
Assert-Contains $appText "Find existing spare" "existing spare route"
Assert-Contains $appText "Generate replacement part" "generated part route"
Assert-Contains $appText "Send to maker/provider" "provider production route"
Assert-Contains $appText "I do not know the part name" "non-expert user CTA"
Assert-NotContains $appText "Five clear steps" "old five-step copy removed"

$css = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/css/reborn.css" -UseBasicParsing -TimeoutSec 15
if ($css.StatusCode -ne 200) { Fail "reborn.css did not return HTTP 200." }
$cssText = [string]$css.Content
Assert-Contains $cssText ".replacement-route-cards" "offer cards layout"
Assert-Contains $cssText ".replacement-route-card" "offer card styling"
Assert-Contains $cssText "repeat(4" "four-step simple stepper"

$loginBody = @{ email = "repair.user@reborn.local"; password = "password" } | ConvertTo-Json -Compress
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody -TimeoutSec 15
if (-not $login.success -or -not $login.token.access_token) { Fail "Repair user login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Repair user login: ok"

$caseBody = @{
    title = "Step 44 replacement part smoke case"
    description = "Created by the repair-first offer architecture smoke test to verify the simplified flow still creates a real repair request. User does not know the exact part code."
    category = "home_appliance"
} | ConvertTo-Json -Compress
$case = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -Headers $headers -ContentType "application/json" -Body $caseBody -TimeoutSec 15
if (-not $case.success -or -not $case.repair_case.id) { Fail "Repair-first case creation failed." }
Ok "Repair-first flow can create a real case: ok"

Write-Host "Step 44 repair-first offer architecture smoke test passed." -ForegroundColor Green
