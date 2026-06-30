$ErrorActionPreference = "Stop"

$BaseUrl = if ($env:REBORN_BASE_URL) { $env:REBORN_BASE_URL } else { "http://127.0.0.1:8080" }

function Ok($message) {
  Write-Host $message -ForegroundColor Green
}

function Info($message) {
  Write-Host $message -ForegroundColor Cyan
}

function Login-As($email) {
  $body = @{ email = $email; password = "password" } | ConvertTo-Json -Compress
  $login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $body -TimeoutSec 10
  if (-not $login.token.access_token) { throw "Login failed for $email" }
  return @{ Token = $login.token.access_token; User = $login.user }
}

function AuthHeaders($token) {
  return @{ Authorization = "Bearer $token" }
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

    if (-not $response.IsSuccessStatusCode) {
      throw "Upload failed with HTTP $([int]$response.StatusCode): $text"
    }

    return $text | ConvertFrom-Json
  } finally {
    if ($fileStream) { $fileStream.Dispose() }
    $content.Dispose()
    $client.Dispose()
  }
}

Info "Checking Re-born Repair Upload & AI Recognition API at $BaseUrl"
Info "Step 48.4: generic CI upload smoke uses deterministic_smoke recognition to avoid live Gemini/OpenAI latency, quota and network dependency."

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$repair = Login-As "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token
Ok "Login repair.user: ok"

$caseBody = @{
  title = "Step 11 upload recognition smoke case"
  description = "A broken consumer electronics plastic cover with one diagnostic photo uploaded for AI recognition."
  category = "consumer_electronics"
} | ConvertTo-Json -Compress

$created = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/repair-cases" `
  -ContentType "application/json" `
  -Headers $repairHeaders `
  -Body $caseBody `
  -TimeoutSec 10

if (-not $created.repair_case.id) {
  $created | ConvertTo-Json -Depth 10
  throw "Repair case creation failed."
}
$caseId = $created.repair_case.id
Ok "Create repair case: ok"

$tempPng = Join-Path ([System.IO.Path]::GetTempPath()) ("reborn-step11-" + [System.Guid]::NewGuid().ToString() + ".png")
$pngBase64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lxkwWQAAAABJRU5ErkJggg=="
[System.IO.File]::WriteAllBytes($tempPng, [System.Convert]::FromBase64String($pngBase64))

try {
  $uploaded = Invoke-MultipartUpload "$BaseUrl/api/v1/repair-cases/$caseId/attachments" $repair.Token $tempPng
  if (-not $uploaded.attachment.id) {
    $uploaded | ConvertTo-Json -Depth 10
    throw "Upload did not return attachment id."
  }
  $attachmentId = $uploaded.attachment.id
  Ok "Upload attachment: ok"

  $attachments = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/attachments" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if (-not ($attachments.attachments | Where-Object { $_.id -eq $attachmentId })) {
    $attachments | ConvertTo-Json -Depth 10
    throw "Uploaded attachment not found in attachment list."
  }
  Ok "List attachments: ok"

  $recognitionBody = @{ attachment_ids = @($attachmentId); recognition_mode = "deterministic_smoke" } | ConvertTo-Json -Compress
  $job = Invoke-RestMethod `
    -Method POST `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/recognition-jobs" `
    -ContentType "application/json" `
    -Headers $repairHeaders `
    -Body $recognitionBody `
    -TimeoutSec 20

  if ($job.recognition_job.status -ne "completed") {
    $job | ConvertTo-Json -Depth 10
    throw "Recognition job did not complete synchronously."
  }
  if ($job.recognition_job.result_json.recognition_mode -ne "deterministic_smoke") {
    $job | ConvertTo-Json -Depth 10
    throw "Generic CI upload smoke must use deterministic_smoke recognition mode."
  }
  Ok "Deterministic smoke recognition mode: ok"
  if (-not $job.recognition_job.result_json.object_guess) { throw "Recognition result missing object_guess." }
  if (-not $job.recognition_job.result_json.damage_assessment) { throw "Recognition result missing damage_assessment." }
  if (-not $job.recognition_job.result_json.recommended_next_step) { throw "Recognition result missing recommended_next_step." }
  Ok "AI recognition job completed: ok"

  $jobs = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/recognition-jobs" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if (-not ($jobs.recognition_jobs | Where-Object { $_.id -eq $job.recognition_job.id })) {
    $jobs | ConvertTo-Json -Depth 10
    throw "Recognition job not found in list."
  }
  Ok "List recognition jobs: ok"

  $admin = Login-As "admin@reborn.local"
  $adminHeaders = AuthHeaders $admin.Token
  $events = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/domain-events?limit=100" `
    -Headers $adminHeaders `
    -TimeoutSec 10

  $eventNames = @($events.domain_events | ForEach-Object { $_.name })
  foreach ($expected in @("repair.attachment_added", "ai.recognition_requested", "ai.recognition_completed")) {
    if ($eventNames -notcontains $expected) {
      $events | ConvertTo-Json -Depth 10
      throw "Missing domain event: $expected"
    }
    Ok "Domain event ${expected}: ok"
  }
} finally {
  if (Test-Path $tempPng) { Remove-Item $tempPng -Force }
}

Ok "Repair upload and AI recognition smoke test passed."
