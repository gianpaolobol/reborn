param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Ok([string]$Message) { Write-Host $Message -ForegroundColor Green }
function Fail([string]$Message) { throw $Message }
function Assert-Contains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -notlike "*$Needle*") { Fail "Missing Maker Faire wizard marker: $Label" }
    Ok "Maker Faire marker present: $Label"
}
function Assert-NotContains([string]$Text, [string]$Needle, [string]$Label) {
    if ($Text -like "*$Needle*") { Fail "Unexpected Maker Faire visible marker: $Label" }
    Ok "Maker Faire marker absent: $Label"
}
function Slice-Between([string]$Text, [string]$Start, [string]$End, [string]$Label) {
    $startIndex = $Text.IndexOf($Start)
    if ($startIndex -lt 0) { Fail "Could not find start marker for $Label" }
    $endIndex = $Text.IndexOf($End, $startIndex)
    if ($endIndex -lt 0) { Fail "Could not find end marker for $Label" }
    return $Text.Substring($startIndex, $endIndex - $startIndex)
}

Write-Host "Checking Re-born Maker Faire public user wizard at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
Ok "Health: ok"

$index = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/index.html" -UseBasicParsing -TimeoutSec 15
if ($index.StatusCode -ne 200) { Fail "Prototype index did not return HTTP 200." }
$indexText = [string]$index.Content
$topNav = Slice-Between $indexText "<nav class=\"topnav\"" "</nav>" "top nav"
$footer = Slice-Between $indexText "<footer" "</footer>" "footer"

foreach ($marker in @(
    "data-demo-mode=\"makerfaire-public\"",
    "makerfaire_public_wizard_v1",
    "Foto",
    "Analisi",
    "Ricambio",
    "Ripara il mio oggetto",
    "Come funziona",
    ">Demo<"
)) {
    Assert-Contains $indexText $marker $marker
}

foreach ($marker in @("Ripara il mio oggetto", "Come funziona", ">Demo<")) {
    Assert-Contains $topNav $marker "top nav $marker"
}
foreach ($marker in @("Le mie richieste", "Accedi", "Console avanzate", "Governance", "Readiness", "Provider", "Audit", "Investor", "Pilot", "PRODUCT.md")) {
    Assert-NotContains $topNav $marker "top nav $marker"
}
foreach ($marker in @("Console avanzate", "PRODUCT.md", "Governance", "Readiness", "Provider")) {
    Assert-NotContains $footer $marker "footer $marker"
}

$app = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -UseBasicParsing -TimeoutSec 15
if ($app.StatusCode -ne 200) { Fail "app.js did not return HTTP 200." }
$appText = [string]$app.Content
$wizard = Slice-Between $appText "function userRepairWizard()" "function help()" "userRepairWizard"
$behindScenes = Slice-Between $appText "function decisionBehindScenesCard()" "function userRepairWizard()" "decisionBehindScenesCard"
$photoCta = Slice-Between $appText "function currentPhotoCtaLabel()" "function photoCtaHint()" "currentPhotoCtaLabel"
$recognitionPanel = Slice-Between $appText "function recognitionResultPanel()" "function repairPathDecisionPanel()" "recognitionResultPanel"
$recognitionRequest = Slice-Between $appText "async function requestAIRecognitionForAttachments" "function runMockRecognition()" "requestAIRecognitionForAttachments"

foreach ($marker in @(
    "STEP46_MAKERFAIRE_USER_WIZARD_V1",
    "'/advanced': advancedConsoleDirectory",
    "'/capture': userRepairWizard",
    "DEMO_REPAIR_USER",
    "ensureWizardRepairUserSession",
    "handleRepairFilesSelectedAndIdentify(event)",
    "photoCtaContinue"
)) {
    Assert-Contains $appText $marker $marker
}

foreach ($marker in @("single-primary-action", "openRepairPhotoPicker()", "userRepairPrimaryLabel", "userRepairPrimaryActionName")) {
    Assert-Contains $wizard $marker "wizard $marker"
}
foreach ($marker in @("repair path decision engine", "provider matching", "quote/governance guardrails", "#/advanced", "governance", "Provider routing")) {
    Assert-NotContains $behindScenes $marker "behind-scenes $marker"
}
foreach ($marker in @("% confidence", "onclick=\"runRepairPathDecision()\"", "Fallback AI", "Provider AI", "job_id")) {
    Assert-NotContains $recognitionPanel $marker "recognition panel $marker"
}
Assert-Contains $photoCta "photoCtaContinue" "recognized CTA proceeds instead of resetting to upload"
Assert-Contains $recognitionRequest "status: 'live'" "failed recognition keeps live API mode for retry"
Assert-NotContains $recognitionRequest "status: failed ? 'error'" "no retry-breaking error status"

$apiClient = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/api-client.js" -UseBasicParsing -TimeoutSec 15
if ($apiClient.StatusCode -ne 200) { Fail "api-client.js did not return HTTP 200." }
$apiText = [string]$apiClient.Content
Assert-Contains $apiText "AI_RECOGNITION_TIMEOUT_MS = 90000" "90 second AI timeout"
Assert-Contains $apiText "Analisi ancora in corso" "consumer timeout message"
Assert-Contains $apiText "timeoutError.code = 'TIMEOUT'" "timeout error code"

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$secretHits = Get-ChildItem -Path $root -Recurse -File -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -notmatch "[\\/]\.git[\\/]" } |
    Select-String -Pattern "(^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}" -ErrorAction SilentlyContinue
if ($secretHits) { Fail "Potential OpenAI API key found in repository files." }
Ok "Secret scan: no sk- API key pattern found"

Write-Host "Maker Faire public user wizard smoke test passed." -ForegroundColor Green
