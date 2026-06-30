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

Info "Checking Re-born Repair Path Decision Engine API at $BaseUrl"
Info "Step 48.5: path-decision CI smoke uses deterministic_smoke recognition to avoid live Gemini/OpenAI latency, quota and network dependency."

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$repair = Login-As "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token
Ok "Login repair.user: ok"

$caseBody = @{
  title = "Step 12 path decision smoke case"
  description = "A broken consumer electronics plastic cover needs a repair-first path decision after AI recognition. The part is cracked and must return to function."
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

$tempPng = Join-Path ([System.IO.Path]::GetTempPath()) ("reborn-step12-" + [System.Guid]::NewGuid().ToString() + ".png")
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

  $recognitionBody = @{ attachment_ids = @($attachmentId); recognition_mode = "deterministic_smoke" } | ConvertTo-Json -Compress
  $job = Invoke-RestMethod `
    -Method POST `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/recognition-jobs" `
    -ContentType "application/json" `
    -Headers $repairHeaders `
    -Body $recognitionBody `
    -TimeoutSec 10

  if ($job.recognition_job.status -ne "completed") {
    $job | ConvertTo-Json -Depth 10
    throw "Recognition job did not complete synchronously."
  }
  if ($job.recognition_job.result_json.recognition_mode -ne "deterministic_smoke") {
    $job | ConvertTo-Json -Depth 20
    throw "Path-decision CI smoke must use deterministic_smoke recognition mode."
  }
  $recognitionJobId = $job.recognition_job.id
  Ok "Deterministic smoke recognition mode: ok"
  Ok "AI recognition job completed: ok"

  $decisionBody = @{ recognition_job_id = $recognitionJobId } | ConvertTo-Json -Compress
  $decision = Invoke-RestMethod `
    -Method POST `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/repair-path-decisions" `
    -ContentType "application/json" `
    -Headers $repairHeaders `
    -Body $decisionBody `
    -TimeoutSec 10

  if ($decision.decision.status -ne "completed") {
    $decision | ConvertTo-Json -Depth 20
    throw "Repair path decision did not complete."
  }
  if (-not $decision.decision.result_json.recommended_path) { throw "Decision missing recommended_path." }
  if (-not $decision.decision.result_json.ranked_paths) { throw "Decision missing ranked_paths." }
  if (@($decision.decision.result_json.ranked_paths).Count -lt 4) { throw "Decision returned too few ranked paths." }
  Ok "Repair Path Decision Engine completed: ok"

  $paths = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/repair-paths?case_id=$caseId" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if (@($paths.repair_paths).Count -lt 4) {
    $paths | ConvertTo-Json -Depth 20
    throw "Persisted repair paths were not generated."
  }
  Ok "Persisted repair paths: ok"

  $decisions = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/repair-path-decisions" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if (-not ($decisions.repair_path_decisions | Where-Object { $_.id -eq $decision.decision.id })) {
    $decisions | ConvertTo-Json -Depth 20
    throw "Repair path decision not found in list."
  }
  Ok "List repair path decisions: ok"

  $decisionDetail = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/repair-path-decisions/$($decision.decision.id)" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if ($decisionDetail.repair_path_decision.id -ne $decision.decision.id) {
    $decisionDetail | ConvertTo-Json -Depth 20
    throw "Repair path decision detail mismatch."
  }
  Ok "Repair path decision detail: ok"

  $admin = Login-As "admin@reborn.local"
  $adminHeaders = AuthHeaders $admin.Token
  $events = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/domain-events?limit=120" `
    -Headers $adminHeaders `
    -TimeoutSec 10

  $eventNames = @($events.domain_events | ForEach-Object { $_.name })
  foreach ($expected in @("repair.path_decision_requested", "repair.path_decision_completed")) {
    if ($eventNames -notcontains $expected) {
      $events | ConvertTo-Json -Depth 20
      throw "Missing domain event: $expected"
    }
    Ok "Domain event ${expected}: ok"
  }
} finally {
  if (Test-Path $tempPng) { Remove-Item $tempPng -Force }
}

Ok "Repair path decision smoke test passed."
