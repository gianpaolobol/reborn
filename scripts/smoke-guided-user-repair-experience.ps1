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

Assert-Contains $indexText "Repair my object" "primary guided repair nav"
Assert-Contains $indexText "Photos & files" "plain-language evidence nav"
Assert-Contains $indexText "Advanced consoles" "advanced console grouped entry"
Assert-NotContains $indexText "AI Gov" "advanced AI Gov removed from primary nav"
Assert-NotContains $indexText "Geometry</a>" "geometry removed from primary nav"
Assert-NotContains $indexText "Marketplace revenue" "footer no longer exposes full admin list"

$app = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -UseBasicParsing -TimeoutSec 15
if ($app.StatusCode -ne 200) { Fail "app.js did not return HTTP 200." }
$appText = [string]$app.Content
Assert-Contains $appText "function repairGuide" "guided repair route function"
Assert-Contains $appText "Generate the missing replacement part" "first-time user headline"
Assert-Contains $appText "Four steps. One main action at a time" "linear journey copy"
Assert-Contains $appText "advancedConsoleDirectory" "grouped advanced directory"
Assert-Contains $appText "simple-stepper" "reduced four-step progress indicator"
Assert-Contains $appText "UX rule after Step 44" "future navigation governance rule"

$css = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/css/reborn.css" -UseBasicParsing -TimeoutSec 15
if ($css.StatusCode -ne 200) { Fail "reborn.css did not return HTTP 200." }
$cssText = [string]$css.Content
Assert-Contains $cssText ".guided-hero" "guided hero layout CSS"
Assert-Contains $cssText ".guided-step-card" "large readable guided cards"
Assert-Contains $cssText ".replacement-route-card" "replacement route cards"
Assert-Contains $cssText ".advanced-mode-note" "advanced mode warning CSS"

$loginBody = @{ email = "repair.user@reborn.local"; password = "password" } | ConvertTo-Json -Compress
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody -TimeoutSec 15
if (-not $login.success -or -not $login.token.access_token) { Fail "Repair user login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Repair user login: ok"

$caseBody = @{
    title = "Step 43 guided UX smoke case"
    description = "Created by the guided user repair experience smoke test to verify that the simplified UI still connects to the real repair-case API."
    category = "home_appliance"
} | ConvertTo-Json -Compress
$case = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -Headers $headers -ContentType "application/json" -Body $caseBody -TimeoutSec 15
if (-not $case.success -or -not $case.repair_case.id) { Fail "Guided repair case creation failed." }
Ok "Guided repair can create a real case: ok"

Write-Host "Step 43 guided user repair experience smoke test passed." -ForegroundColor Green
