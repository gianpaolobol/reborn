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
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody -TimeoutSec 15
if (-not $login.success -or -not $login.token.access_token) { Fail "Login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login ok"

$status = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ai/photo-recognition/status" -Headers $headers -TimeoutSec 15
$status.photo_recognition_provider | ConvertTo-Json -Depth 10
if (-not $status.photo_recognition_provider.enabled) {
    Write-Warning "OpenAI Vision is not live. Set OPENAI_API_KEY, AI_PHOTO_RECOGNITION_ENABLED=true and make sure API billing/credits are available."
}

Info "Create debug repair case"
$caseBody = @{
    title = "Debug ricambio da immagine prodotto"
    description = "Caso debug Step 47: identificare il ricambio dalla foto, leggendo testi, codici, modello commerciale e funzione. Non usare categorie generiche se OCR o geometria sono specifici."
    category = "replacement_part"
} | ConvertTo-Json -Compress
$created = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -ContentType "application/json" -Headers $headers -Body $caseBody -TimeoutSec 15
$caseId = $created.repair_case.id
Ok "Case created: $caseId"

Info "Upload image"
$uploaded = Invoke-MultipartUpload "$BaseUrl/api/v1/repair-cases/$caseId/attachments" $login.token.access_token $ImagePath
$attachmentId = $uploaded.attachment.id
Ok "Attachment uploaded: $attachmentId"

Info "Run AI recognition"
$recognitionBody = @{ attachment_ids = @($attachmentId) } | ConvertTo-Json -Compress
$job = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases/$caseId/recognition-jobs" -ContentType "application/json" -Headers $headers -Body $recognitionBody -TimeoutSec 180
if (-not $job.success) { Fail "Recognition failed." }

$result = $job.recognition_job.result_json
Ok "Recognition status: $($job.recognition_job.status)"
$result | ConvertTo-Json -Depth 20
