param(
    [string]$BaseUrl = "http://127.0.0.1:8080",
    [string[]]$ImagePath = @(),
    [string]$Email = "repair.user@reborn.local",
    [string]$Password = "password"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Fail($message) { throw $message }
function Info($message) { Write-Host $message -ForegroundColor Cyan }
function Ok($message) { Write-Host $message -ForegroundColor Green }
function Warn($message) { Write-Host $message -ForegroundColor Yellow }
function AuthHeaders($token) { return @{ Authorization = "Bearer $token" } }
function ErrorResponseBody($errorRecord) {
    try {
        $response = $errorRecord.Exception.Response
        if ($null -eq $response) { return $errorRecord.Exception.Message }
        $stream = $response.GetResponseStream()
        if ($null -eq $stream) { return $errorRecord.Exception.Message }
        $reader = [System.IO.StreamReader]::new($stream)
        try { return $reader.ReadToEnd() } finally { $reader.Dispose() }
    } catch {
        return $errorRecord.Exception.Message
    }
}


function ShortText($value, [int]$max = 260) {
    if ($null -eq $value) { return "" }
    $text = [string]$value
    $text = $text -replace "\s+", " "
    if ($text.Length -le $max) { return $text }
    return $text.Substring(0, $max) + "..."
}

function Print-ProviderConfiguration($providerStatus) {
    Info "Provider configuration detected by backend:"
    if ($providerStatus.provider_order) {
        Write-Host ("  Order: " + ($providerStatus.provider_order -join " -> ")) -ForegroundColor Cyan
    }
    if ($providerStatus.quality_gate) {
        $gateEnabled = [bool]$providerStatus.quality_gate.enabled
        Info "  Quality gate enabled: $gateEnabled"
        if ($providerStatus.quality_gate.verifier_providers) {
            Info ("  Quality verifiers: " + ($providerStatus.quality_gate.verifier_providers -join " -> "))
        }
    }

    if (-not $providerStatus.providers) {
        Warn "  No provider detail map returned by backend status endpoint."
        return
    }

    $providerNames = @()
    if ($providerStatus.provider_order) {
        $providerNames += @($providerStatus.provider_order)
    }
    foreach ($property in $providerStatus.providers.PSObject.Properties) {
        if ($providerNames -notcontains $property.Name) { $providerNames += $property.Name }
    }

    foreach ($name in $providerNames) {
        $provider = $providerStatus.providers.$name
        if ($null -eq $provider) { continue }
        $enabled = [bool]$provider.enabled
        $configured = [bool]$provider.configured
        $state = if ($enabled -and $configured) { "ready" } elseif (-not $enabled) { "disabled" } else { "not_configured" }
        $line = "  - $name : $state | enabled=$enabled configured=$configured | mode=$($provider.mode) | model=$($provider.model)"
        if ($state -eq "ready") { Ok $line } elseif ($state -eq "disabled") { Warn $line } else { Warn $line }
        if ($provider.missing_configuration -and $provider.missing_configuration.Count -gt 0) {
            Warn ("    missing: " + ($provider.missing_configuration -join ", "))
        }
    }
}

function Print-ProviderAttempts($result) {
    $router = $result.ai_provider.provider_router
    if ($null -eq $router) {
        Warn "Provider attempts: not returned by backend. Apply the updated MultiProviderPhotoRecognitionGateway patch."
        return
    }

    if ($router.provider_order) {
        Info ("Provider router order used: " + ($router.provider_order -join " -> "))
    }
    if ($router.selected_provider) {
        Info "Provider router selected: $($router.selected_provider)"
    }
    if ($router.note) {
        Warn "Provider router note: $($router.note)"
    }

    $attempts = @($router.provider_attempts)
    if (-not $attempts -or $attempts.Count -eq 0) {
        Warn "Provider attempts: empty. Backend did not include per-provider attempt details."
        return
    }

    Info "Provider attempts detail:"
    foreach ($attempt in $attempts) {
        $name = [string]$attempt.provider
        $status = [string]$attempt.status
        $elapsed = if ($attempt.elapsed_ms -ne $null) { " | elapsed_ms=$($attempt.elapsed_ms)" } else { "" }
        $line = "  - $name : $status | enabled=$($attempt.enabled) configured=$($attempt.configured) | mode=$($attempt.mode) | model=$($attempt.model)$elapsed"
        if ($status -eq "success") { Ok $line } else { Warn $line }
        if ($attempt.recognition_mode) { Write-Host "    recognition_mode: $($attempt.recognition_mode)" }
        if ($attempt.provider_status) { Write-Host "    provider_status: $($attempt.provider_status)" }
        if ($attempt.identification_status) { Write-Host "    identification_status: $($attempt.identification_status)" }
        if ($attempt.part_guess) { Write-Host "    part_guess: $($attempt.part_guess)" }
        if ($attempt.visible_text_count -ne $null) { Write-Host "    visible_text_count: $($attempt.visible_text_count)" }
        if ($attempt.quality_gate) {
            Write-Host "    quality_score: $($attempt.quality_gate.quality_score)/100"
            Write-Host "    quality_status: $($attempt.quality_gate.verification_status)"
            if ($attempt.quality_gate.verification_reasons -and $attempt.quality_gate.verification_reasons.Count -gt 0) {
                Warn ("    verification: " + (($attempt.quality_gate.verification_reasons | Select-Object -First 2) -join " | "))
            }
        }
        if ($attempt.missing_configuration -and $attempt.missing_configuration.Count -gt 0) {
            Warn ("    missing: " + ($attempt.missing_configuration -join ", "))
        }
        if ($attempt.reason) {
            Warn ("    reason: " + (ShortText $attempt.reason 500))
        }
    }
}

function Invoke-MultipartUpload($uri, $token, $filePath) {
    Add-Type -AssemblyName System.Net.Http
    $client = [System.Net.Http.HttpClient]::new()
    $client.Timeout = [TimeSpan]::FromSeconds(60)
    $content = [System.Net.Http.MultipartFormDataContent]::new()
    $fileStream = $null
    try {
        $client.DefaultRequestHeaders.Authorization = [System.Net.Http.Headers.AuthenticationHeaderValue]::new("Bearer", $token)
        $mime = switch ([System.IO.Path]::GetExtension($filePath).ToLowerInvariant()) {
            ".jpg" { "image/jpeg" }
            ".jpeg" { "image/jpeg" }
            ".png" { "image/png" }
            ".webp" { "image/webp" }
            default { "application/octet-stream" }
        }
        $fileStream = [System.IO.File]::OpenRead($filePath)
        $fileContent = [System.Net.Http.StreamContent]::new($fileStream)
        $fileContent.Headers.ContentType = [System.Net.Http.Headers.MediaTypeHeaderValue]::Parse($mime)
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

if (-not $ImagePath -or $ImagePath.Count -eq 0) {
    Fail "Passa una o più foto: powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-photo-recognition-live.ps1 -ImagePath C:\path\foto1.jpg,C:\path\foto2.jpg"
}
foreach ($path in $ImagePath) {
    if (-not (Test-Path $path)) { Fail "ImagePath non trovato: $path" }
}

Info "Checking Re-born AI photo recognition live flow at $BaseUrl"
$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
Ok "Health: ok"

$loginBody = @{ email = $Email; password = $Password } | ConvertTo-Json -Compress
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody -TimeoutSec 15
if (-not $login.success -or -not $login.token.access_token) { Fail "Login failed for $Email" }
$headers = AuthHeaders $login.token.access_token
Ok "Login: ok ($($login.user.email))"

$status = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ai/photo-recognition/status" -Headers $headers -TimeoutSec 15
$status.photo_recognition_provider | ConvertTo-Json -Depth 10
Print-ProviderConfiguration $status.photo_recognition_provider
if (-not $status.photo_recognition_provider.configured) {
    Warn "Nessun provider selezionato risulta configurato. Controlla le API key in .env."
} elseif (-not $status.photo_recognition_provider.enabled) {
    Warn "Photo recognition provider configurato ma non enabled. Controlla AI_PHOTO_RECOGNITION_ENABLED=true."
} else {
    Ok "Photo recognition router live mode: $($status.photo_recognition_provider.mode)"
}

$caseBody = @{
    title = "Debug live AI photo recognition"
    description = "Real uploaded photo for replacement-part recognition debug. Identify the likely broken component and missing dimensions."
    category = "home_appliance"
} | ConvertTo-Json -Compress
try {
    $created = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -ContentType "application/json" -Headers $headers -Body $caseBody -TimeoutSec 15
} catch {
    Fail "Repair case creation failed. Response: $(ErrorResponseBody $_)"
}
$caseId = $created.repair_case.id
if (-not $caseId) { Fail "Repair case creation did not return repair_case.id. Response: $($created | ConvertTo-Json -Depth 10)" }
Ok "Repair case created: $caseId"

$attachmentIds = @()
foreach ($path in $ImagePath) {
    $uploaded = Invoke-MultipartUpload "$BaseUrl/api/v1/repair-cases/$caseId/attachments" $login.token.access_token $path
    $attachmentIds += $uploaded.attachment.id
    Ok "Photo uploaded: $($uploaded.attachment.id) · $([System.IO.Path]::GetFileName($path))"
}

$recognitionBody = @{ attachment_ids = $attachmentIds } | ConvertTo-Json -Compress
Info "Calling AI recognition. This may take up to 90 seconds..."
$job = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases/$caseId/recognition-jobs" -ContentType "application/json" -Headers $headers -Body $recognitionBody -TimeoutSec 100

$job.recognition_job | ConvertTo-Json -Depth 20
if ($job.recognition_job.status -ne "completed") {
    Fail "Recognition job status is $($job.recognition_job.status). Error: $($job.recognition_job.error_message)"
}
if (-not $job.recognition_job.result_json) {
    Fail "Recognition completed without result_json. Check storage/logs and PHP server output."
}

$result = $job.recognition_job.result_json
Print-ProviderAttempts $result
Ok "Recognition mode: $($result.recognition_mode)"
Ok "Identification status: $($result.identification.status)"
Ok "Source image type: $($result.identification.source_image_type)"
Ok "Part guess: $($result.object_guess.label)"
if ($result.identification.part_number) { Ok "Part number: $($result.identification.part_number)" }
if ($result.part_spec.known_dimensions) { Ok "Known dimensions: $($result.part_spec.known_dimensions -join '; ')" }
if ($result.identification.visible_text) { Ok "Visible text: $($result.identification.visible_text -join ' | ')" }
Ok "Next step: $($result.recommended_next_step.path)"
if ($result.quality_gate) {
    Info "Recognition quality gate:"
    Write-Host "  score: $($result.quality_gate.quality_score)/100"
    Write-Host "  status: $($result.quality_gate.verification_status)"
    Write-Host "  accept_as_final: $($result.quality_gate.accept_as_final)"
    Write-Host "  requires_verification: $($result.quality_gate.requires_verification)"
    if ($result.quality_gate.verification_reasons -and $result.quality_gate.verification_reasons.Count -gt 0) {
        Warn ("  reasons: " + ($result.quality_gate.verification_reasons -join " | "))
    }
}
if ($result.verification -and $result.verification.status) {
    Warn "Verification status: $($result.verification.status) - $($result.verification.reason)"
}
if ($result.ai_provider.status -eq "error_fallback") {
    Warn "Fallback used. Provider error: $($result.ai_provider.error)"
}
