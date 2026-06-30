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

Write-Host "Checking Re-born Step 47 AI Vision Quality Profile at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
foreach ($capability in @("ai_vision_quality_profile", "max_vision_ocr_reference_identification", "openai_photo_recognition_api")) {
    if (@($health.capabilities) -notcontains $capability) { Fail "Health capability missing: $capability" }
    Ok "Health capability present: $capability"
}

$repair = Login-As "repair.user@reborn.local"
$headers = AuthHeaders $repair.Token
$status = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ai/photo-recognition/status" -Headers $headers -TimeoutSec 15
if (-not $status.success -or -not $status.photo_recognition_provider) { Fail "Photo recognition provider status missing." }
$provider = $status.photo_recognition_provider
foreach ($field in @("quality_profile", "image_detail", "web_search_enabled", "reasoning_effort", "max_images", "max_image_bytes", "billing_note")) {
    if ($null -eq $provider.PSObject.Properties[$field]) { Fail "Provider status missing Step 47 field: $field" }
    Ok "Provider Step 47 field present: $field"
}
if ($provider.quality_profile -ne "max_vision_ocr_reference_part_identification_v2") { Fail "Unexpected quality profile: $($provider.quality_profile)" }
Ok "Provider quality profile: ok"

# The PHP source is normally not web-served. Read it from disk instead.
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$GatewayPath = Join-Path $Root "src/AI/Application/OpenAIPhotoRecognitionGateway.php"
$AppPath = Join-Path $Root "public/prototype/assets/js/app.js"
$ConfigPath = Join-Path $Root "config/ai.php"
$ReadmePath = Join-Path $Root "README.md"

foreach ($path in @($GatewayPath, $AppPath, $ConfigPath, $ReadmePath)) {
    if (-not (Test-Path $path)) { Fail "Missing expected file: $path" }
}

$gatewayText = Get-Content -Raw -Path $GatewayPath
$appText = Get-Content -Raw -Path $AppPath
$configText = Get-Content -Raw -Path $ConfigPath
$readmeText = Get-Content -Raw -Path $ReadmePath

foreach ($marker in @(
    "reference_image_ocr_part_identification_v2",
    'detail'' => $this->imageDetail()',
    "web_search",
    "shouldRetryForBetterIdentification",
    "refineResultFromVisibleText",
    "165314 Dishwasher Lower Rack Wheel",
    "ruota cestello inferiore lavastoviglie",
    "commercial_name",
    "possible_brands",
    "compatibility_clues",
    "manufacturing_features"
)) {
    if ($gatewayText -notlike "*$marker*") { Fail "Missing Step 47 gateway marker: $marker" }
    Ok "Gateway marker present: $marker"
}

foreach ($marker in @(
    "OPENAI_VISION_MODEL', 'gpt-5.5'",
    "OPENAI_VISION_DETAIL",
    "OPENAI_VISION_WEB_SEARCH_ENABLED",
    "OPENAI_REASONING_EFFORT",
    "OPENAI_VISION_MAX_OUTPUT_TOKENS"
)) {
    if ($configText -notlike "*$marker*") { Fail "Missing Step 47 config marker: $marker" }
    Ok "Config marker present: $marker"
}

foreach ($marker in @(
    "Nome commerciale",
    "Marche compatibili possibili",
    "Indizi di compatibilit",
    "Riconoscimento live necessario",
    "Dettaglio immagine",
    "web_search_enabled"
)) {
    if ($appText -notlike "*$marker*") { Fail "Missing Step 47 prototype marker: $marker" }
    Ok "Prototype marker present: $marker"
}

foreach ($marker in @(
    "Step 47",
    "OPENAI_VISION_MODEL=gpt-5.5",
    "OPENAI_VISION_DETAIL=original",
    "OPENAI_VISION_WEB_SEARCH_ENABLED=true",
    "ChatGPT Plus"
)) {
    if ($readmeText -notlike "*$marker*") { Fail "Missing Step 47 README marker: $marker" }
    Ok "README marker present: $marker"
}

Write-Host "Step 47 AI Vision Quality Profile smoke test passed." -ForegroundColor Green
