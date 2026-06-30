param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AuthHeaders($token) { return @{ Authorization = "Bearer $token" } }
function Login-As($email) {
    $body = @{ email = $email; password = "password" } | ConvertTo-Json -Compress
    $login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $body -TimeoutSec 15
    if (-not $login.success -or -not $login.token.access_token) { Fail "Login failed for $email" }
    return @{ Token = $login.token.access_token; User = $login.user }
}

Write-Host "Checking Re-born Step 48.2 Gemini-only Vision provider at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
foreach ($capability in @("gemini_vision_provider", "multi_provider_vision_router")) {
    if (@($health.capabilities) -notcontains $capability) { Fail "Health capability missing: $capability" }
    Ok "Health capability present: $capability"
}
if (@($health.capabilities) -contains "google_cloud_vision_ocr") { Fail "Google Cloud Vision capability must not be exposed in Gemini-only mode." }

$repair = Login-As "repair.user@reborn.local"
$status = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ai/photo-recognition/status" -Headers (AuthHeaders $repair.Token) -TimeoutSec 15
if (-not $status.success -or -not $status.photo_recognition_provider) { Fail "Photo recognition provider status missing." }
$provider = $status.photo_recognition_provider
foreach ($field in @("provider_order", "providers", "step48_quality_profile", "billing_note")) {
    if ($null -eq $provider.PSObject.Properties[$field]) { Fail "Provider status missing Step 48.1 field: $field" }
    Ok "Provider Step 48.1 field present: $field"
}
if ($provider.step48_quality_profile -ne "gemini_vision_repair_identification_v1") { Fail "Unexpected Step 48.1 quality profile: $($provider.step48_quality_profile)" }
if ($null -eq $provider.providers.gemini) { Fail "Missing gemini nested provider status." }
if ($null -eq $provider.providers.openai) { Fail "Missing openai nested provider status." }
Ok "Provider router status: ok"

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$GeminiGatewayPath = Join-Path $Root "src/AI/Application/GeminiGooglePhotoRecognitionGateway.php"
$RouterGatewayPath = Join-Path $Root "src/AI/Application/MultiProviderPhotoRecognitionGateway.php"
$ConfigPath = Join-Path $Root "config/ai.php"
$EnvPath = Join-Path $Root ".env.example"
$ReadmePath = Join-Path $Root "README.md"
foreach ($path in @($GeminiGatewayPath, $RouterGatewayPath, $ConfigPath, $EnvPath, $ReadmePath)) {
    if (-not (Test-Path $path)) { Fail "Missing expected file: $path" }
}

$geminiText = Get-Content -Raw -Path $GeminiGatewayPath
$routerText = Get-Content -Raw -Path $RouterGatewayPath
$configText = Get-Content -Raw -Path $ConfigPath
$envText = Get-Content -Raw -Path $EnvPath
$readmeText = Get-Content -Raw -Path $ReadmePath

foreach ($marker in @(
    "Gemini-only vision provider",
    "live_gemini_vision",
    "gemini_vision_reference_part_identification_v1",
    "gemini_vision_api",
    "gemini_vision_api_quality_retry",
    "looksLikeSuccessfulJsonResponse",
    "returned HTTP 0",
    "165314 Dishwasher Lower Rack Wheel",
    "Ruota del cestello inferiore per lavastoviglie"
)) {
    if ($geminiText -notlike "*$marker*") { Fail "Missing Step 48.1 Gemini gateway marker: $marker" }
    Ok "Gemini gateway marker present: $marker"
}
foreach ($marker in @("MultiProviderPhotoRecognitionGateway", "provider_order", "fallback_after_all_providers", "gemini")) {
    if ($routerText -notlike "*$marker*") { Fail "Missing Step 48.1 router marker: $marker" }
    Ok "Router marker present: $marker"
}
foreach ($marker in @("AI_VISION_PROVIDER_ORDER", "GEMINI_API_KEY", "GEMINI_VISION_MODEL")) {
    if ($configText -notlike "*$marker*" -and $envText -notlike "*$marker*") { Fail "Missing Step 48.1 config/env marker: $marker" }
    Ok "Config/env marker present: $marker"
}
foreach ($forbidden in @("GOOGLE_CLOUD_VISION_API_KEY", "GOOGLE_CLOUD_VISION_BASE_URL", "GOOGLE_CLOUD_VISION_FEATURES")) {
    if ($configText -like "*$forbidden*" -or $envText -like "*$forbidden*") { Fail "Forbidden Google Cloud marker still present in config/env: $forbidden" }
    Ok "Forbidden Google Cloud config marker absent: $forbidden"
}
foreach ($marker in @("Step 48.1", "Gemini-only Vision", "AI_PHOTO_RECOGNITION_PROVIDER=auto", "AI_VISION_PROVIDER_ORDER=gemini,openai")) {
    if ($readmeText -notlike "*$marker*") { Fail "Missing Step 48.1 README marker: $marker" }
    Ok "README marker present: $marker"
}

Write-Host "Step 48.2 Gemini-only Vision provider smoke test passed." -ForegroundColor Green
