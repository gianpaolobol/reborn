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
function Invoke-MultipartUpload($uri, $token, $filePath) {
    Add-Type -AssemblyName System.Net.Http
    $client = [System.Net.Http.HttpClient]::new()
    $content = [System.Net.Http.MultipartFormDataContent]::new()
    $fileStream = $null
    try {
        $client.DefaultRequestHeaders.Authorization = [System.Net.Http.Headers.AuthenticationHeaderValue]::new("Bearer", $token)
        $fileStream = [System.IO.File]::OpenRead($filePath)
        $fileContent = [System.Net.Http.StreamContent]::new($fileStream)
        $fileContent.Headers.ContentType = [System.Net.Http.Headers.MediaTypeHeaderValue]::Parse("image/png")
        $content.Add($fileContent, "file", [System.IO.Path]::GetFileName($filePath))
        $content.Add([System.Net.Http.StringContent]::new("diagnostic_photo"), "kind")
        $response = $client.PostAsync($uri, $content).GetAwaiter().GetResult()
        $text = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        if (-not $response.IsSuccessStatusCode) { Fail "Upload failed with HTTP $([int]$response.StatusCode): $text" }
        return $text | ConvertFrom-Json
    } finally {
        if ($fileStream) { $fileStream.Dispose() }
        $content.Dispose()
        $client.Dispose()
    }
}

Write-Host "Checking Re-born Step 45/48.6 AI Photo Recognition & Replacement Brief at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
Ok "Health: ok"

$capabilities = @($health.capabilities)
foreach ($capability in @("openai_photo_recognition_api", "ai_replacement_part_brief", "guided_missing_inputs", "replacement_part_generation_wizard")) {
    if ($capabilities -notcontains $capability) { Fail "Health capability missing: $capability" }
    Ok "Health capability present: $capability"
}

$repair = Login-As "repair.user@reborn.local"
$headers = AuthHeaders $repair.Token
Ok "Repair user login: ok"

$status = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ai/photo-recognition/status" -Headers $headers -TimeoutSec 15
if (-not $status.success -or -not $status.photo_recognition_provider) { Fail "Photo recognition provider status missing." }
$provider = [string]$status.photo_recognition_provider.provider
# Step 48.6: the structural Step 45 smoke must remain compatible with both the legacy OpenAI-only status
# and the newer auto/gemini multi-provider router. It validates API shape, not live provider choice.
if (@("openai", "gemini", "auto") -notcontains $provider) { Fail "Expected OpenAI/Gemini/auto provider status, got: $provider" }
if ($provider -eq "auto") {
    if (-not $status.photo_recognition_provider.providers) { Fail "Auto provider router status missing providers map." }
    if (-not $status.photo_recognition_provider.provider_order) { Fail "Auto provider router status missing provider_order." }
    Ok "Photo recognition provider status: ok (auto router: $($status.photo_recognition_provider.provider_order -join ','))"
} else {
    if (-not $status.photo_recognition_provider.capability) { Fail "Provider capability missing." }
    Ok "Photo recognition provider status: ok ($($status.photo_recognition_provider.mode))"
}

$caseBody = @{
    title = "Step 45 photo recognition smoke case"
    description = "A small broken plastic clip from an appliance drawer. The user does not know the exact part name and wants Re-born to generate a replacement part brief from a photo."
    category = "home_appliance"
} | ConvertTo-Json -Compress
$created = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -ContentType "application/json" -Headers $headers -Body $caseBody -TimeoutSec 15
if (-not $created.success -or -not $created.repair_case.id) { Fail "Repair case creation failed." }
$caseId = $created.repair_case.id
Ok "Repair case created: ok"

$tempPng = Join-Path ([System.IO.Path]::GetTempPath()) ("reborn-step45-" + [System.Guid]::NewGuid().ToString() + ".png")
$pngBase64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lxkwWQAAAABJRU5ErkJggg=="
[System.IO.File]::WriteAllBytes($tempPng, [System.Convert]::FromBase64String($pngBase64))

try {
    $uploaded = Invoke-MultipartUpload "$BaseUrl/api/v1/repair-cases/$caseId/attachments" $repair.Token $tempPng
    if (-not $uploaded.attachment.id) { Fail "Upload did not return attachment id." }
    $attachmentId = $uploaded.attachment.id
    Ok "Diagnostic photo uploaded: ok"

    $recognitionBody = @{ attachment_ids = @($attachmentId); recognition_mode = "deterministic_smoke" } | ConvertTo-Json -Compress
    $job = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases/$caseId/recognition-jobs" -ContentType "application/json" -Headers $headers -Body $recognitionBody -TimeoutSec 100
    if (-not $job.success -or $job.recognition_job.status -ne "completed") {
        $job | ConvertTo-Json -Depth 10
        Fail "Recognition job did not complete."
    }
    $result = $job.recognition_job.result_json
    if ($result.recognition_mode -ne "deterministic_smoke") { Fail "Step 45/48 structural CI smoke must use deterministic_smoke recognition mode." }
    Ok "Deterministic smoke recognition mode: ok"
    if (-not $result.ai_provider) { Fail "Recognition result missing ai_provider metadata." }
    if (-not $result.recognition_mode) { Fail "Recognition result missing recognition_mode." }
    if (-not $result.identification) { Fail "Recognition result missing identification metadata." }
    if (-not $result.part_spec) { Fail "Recognition result missing part_spec metadata." }
    if (-not $result.object_guess.object_context) { Fail "Recognition result missing object_context." }
    if (-not $result.replacement_part_brief) { Fail "Recognition result missing replacement_part_brief." }
    if (-not $result.replacement_part_brief.plain_language_summary) { Fail "Brief missing plain_language_summary." }
    if (-not $result.replacement_part_brief.critical_dimensions) { Fail "Brief missing critical_dimensions." }
    if (-not $result.replacement_part_brief.photo_requirements) { Fail "Brief missing photo_requirements." }
    if (-not $result.replacement_part_brief.user_questions) { Fail "Brief missing user_questions." }
    if (-not $result.recommended_next_step.path) { Fail "Recognition result missing recommended next path." }
    Ok "AI photo recognition replacement brief: ok ($($result.recognition_mode))"

    $app = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/assets/js/app.js" -UseBasicParsing -TimeoutSec 15
    $appText = [string]$app.Content
    # Keep markers ASCII-safe for Windows PowerShell 5.1 encoding behavior.
    foreach ($marker in @("Primo sguardo AI", "Carica foto e identifica il pezzo", "Carica altre immagini", "pezzo riconosciuto", "servono altre immagini", "Testo letto nell", "Codice pezzo", "Dimensioni lette", "sourceImageLabel", "openRepairPhotoPicker", "handleRepairFilesSelectedAndIdentify", "language-switch", "syncStaticChromeLanguage")) {
        if ($appText -notlike "*$marker*") { Fail "Missing Step 45.4 prototype marker: $marker" }
        Ok "Prototype marker present: $marker"
    }
} finally {
    if (Test-Path $tempPng) { Remove-Item $tempPng -Force }
}

Write-Host "Step 45/48.6 AI photo recognition replacement brief smoke test passed." -ForegroundColor Green
