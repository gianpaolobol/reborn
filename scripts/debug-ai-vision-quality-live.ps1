param(
    [string]$BaseUrl = "http://127.0.0.1:8080",
    [Parameter(Mandatory = $true)][string]$ImagePath,
    [string]$Email = "repair.user@reborn.local",
    [string]$Password = "password"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Fail($message) { throw $message }
function Info($message) { Write-Host $message -ForegroundColor Cyan }
function Ok($message) { Write-Host $message -ForegroundColor Green }

function Invoke-RebornJson($method, $uri, $headers, $body, $timeoutSec = 15) {
    try {
        if ($null -eq $headers) {
            return Invoke-RestMethod -Method $method -Uri $uri -ContentType "application/json" -Body $body -TimeoutSec $timeoutSec
        }

        return Invoke-RestMethod -Method $method -Uri $uri -ContentType "application/json" -Headers $headers -Body $body -TimeoutSec $timeoutSec
    } catch {
        $statusCode = $null
        $responseBody = ""
        if ($_.Exception.Response) {
            try { $statusCode = [int]$_.Exception.Response.StatusCode } catch { $statusCode = "unknown" }
            try {
                $reader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
                $responseBody = $reader.ReadToEnd()
                $reader.Dispose()
            } catch { $responseBody = "Unable to read response body." }
        }

        if ($responseBody -ne "") {
            Fail "${method} ${uri} failed with HTTP ${statusCode}: ${responseBody}"
        }

        Fail "${method} ${uri} failed: $($_.Exception.Message)"
    }
}

function Content-Type-For($path) {
    $extension = [System.IO.Path]::GetExtension($path).ToLowerInvariant()
    switch ($extension) {
        ".jpg" { return "image/jpeg" }
        ".jpeg" { return "image/jpeg" }
        ".png" { return "image/png" }
        ".webp" { return "image/webp" }
        default { return "application/octet-stream" }
    }
}

function Invoke-MultipartUpload($uri, $token, $filePath) {
    Add-Type -AssemblyName System.Net.Http
    $client = [System.Net.Http.HttpClient]::new()
    $content = [System.Net.Http.MultipartFormDataContent]::new()
    $fileStream = $null
    try {
        $client.Timeout = [TimeSpan]::FromSeconds(120)
        $client.DefaultRequestHeaders.Authorization = [System.Net.Http.Headers.AuthenticationHeaderValue]::new("Bearer", $token)
        $fileStream = [System.IO.File]::OpenRead($filePath)
        $fileContent = [System.Net.Http.StreamContent]::new($fileStream)
        $fileContent.Headers.ContentType = [System.Net.Http.Headers.MediaTypeHeaderValue]::Parse((Content-Type-For $filePath))
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

if (-not (Test-Path $ImagePath)) { Fail "Image not found: $ImagePath" }

Info "Login as $Email"
$loginBody = @{ email = $Email; password = $Password } | ConvertTo-Json -Compress
$login = Invoke-RebornJson "POST" "$BaseUrl/api/v1/auth/login" $null $loginBody 15
if (-not $login.success -or -not $login.token.access_token) { Fail "Login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login ok"

$status = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ai/photo-recognition/status" -Headers $headers -TimeoutSec 15
$status.photo_recognition_provider | ConvertTo-Json -Depth 10
if (-not $status.photo_recognition_provider.enabled) {
    Write-Warning "Live AI Vision provider is not enabled. Set GEMINI_API_KEY or OPENAI_API_KEY, AI_PHOTO_RECOGNITION_ENABLED=true and make sure API quota/billing is available."
}

Info "Create debug repair case"
$caseBody = @{
    title = "Debug ricambio da immagine prodotto"
    description = "Caso debug Step 47: identificare il ricambio dalla foto, leggendo testi, codici, modello commerciale e funzione. Non usare categorie generiche se OCR o geometria sono specifici."
    category = "home_appliance"
} | ConvertTo-Json -Compress
$created = Invoke-RebornJson "POST" "$BaseUrl/api/v1/repair-cases" $headers $caseBody 15
$caseId = $created.repair_case.id
Ok "Case created: $caseId"

Info "Upload image"
$uploaded = Invoke-MultipartUpload "$BaseUrl/api/v1/repair-cases/$caseId/attachments" $login.token.access_token $ImagePath
$attachmentId = $uploaded.attachment.id
Ok "Attachment uploaded: $attachmentId"
Write-Host "Attachment details:" -ForegroundColor DarkCyan
$uploaded.attachment | ConvertTo-Json -Depth 10

Info "Run AI recognition"
$recognitionBody = @{ attachment_ids = @($attachmentId) } | ConvertTo-Json -Compress
$job = Invoke-RebornJson "POST" "$BaseUrl/api/v1/repair-cases/$caseId/recognition-jobs" $headers $recognitionBody 180
Write-Host "Recognition raw response:" -ForegroundColor DarkCyan
$job | ConvertTo-Json -Depth 30

if (-not $job.success) {
    $errorMessage = "Recognition API returned success=false."
    if ($job.error -and $job.error.message) {
        $errorMessage = "$errorMessage $($job.error.code): $($job.error.message)"
    }
    Fail $errorMessage
}

if (-not $job.recognition_job) {
    Fail "Recognition response did not contain recognition_job. Full response was printed above."
}

if ($job.recognition_job.status -eq "failed") {
    $message = [string]$job.recognition_job.error_message
    if ($message -eq "") { $message = "Recognition job failed without error_message." }
    Fail "Recognition job failed: $message"
}

$result = $job.recognition_job.result_json
Ok "Recognition status: $($job.recognition_job.status)"
if ($null -eq $result) {
    Fail "Recognition job completed without result_json. Full response was printed above."
}
$result | ConvertTo-Json -Depth 20

if ($result.recognition_mode -eq "deterministic_fallback_no_openai_key") {
    Fail "Recognition fell back to RequestRecognitionJobService mockResult. The provider status may be configured, but the gateway returned null before calling a live Vision provider. Check attachment MIME/stored_path/readability; valid JPG/PNG/WebP uploads should be passed to Gemini/OpenAI."
}

if ($result.ai_provider.status -eq "error_fallback") {
    Write-Warning "Live Vision provider was attempted but failed: $($result.ai_provider.error)"
    Fail "Live Vision attempted but returned provider fallback. Fix the provider error above before judging recognition quality."
}
