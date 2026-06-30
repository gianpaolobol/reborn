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

Info "Checking Re-born Provider Match & Quote Engine API at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$repair = Login-As "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token
Ok "Login repair.user: ok"

$caseBody = @{
  title = "Step 13 provider match smoke case"
  description = "A cracked plastic cover on a consumer electronics device needs provider validation, local repair production and a preliminary quote."
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

$tempPng = Join-Path ([System.IO.Path]::GetTempPath()) ("reborn-step13-" + [System.Guid]::NewGuid().ToString() + ".png")
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
    $job | ConvertTo-Json -Depth 20
    throw "Recognition job did not complete synchronously."
  }
  if ($job.recognition_job.result_json.recognition_mode -ne "deterministic_smoke") {
    $job | ConvertTo-Json -Depth 20
    throw "Provider-match CI smoke must use deterministic_smoke recognition mode."
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
  $decisionId = $decision.decision.id
  Ok "Repair path decision completed: ok"

  $matchBody = @{ repair_path_decision_id = $decisionId } | ConvertTo-Json -Compress
  $match = Invoke-RestMethod `
    -Method POST `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/provider-matches" `
    -ContentType "application/json" `
    -Headers $repairHeaders `
    -Body $matchBody `
    -TimeoutSec 10

  if ($match.provider_match.status -ne "completed") {
    $match | ConvertTo-Json -Depth 30
    throw "Provider match did not complete."
  }
  if (-not $match.provider_match.result_json.ranked_providers) { throw "Provider match missing ranked_providers." }
  if (@($match.provider_match.result_json.ranked_providers).Count -lt 1) { throw "Provider match returned no providers." }
  $providerMatchId = $match.provider_match.id
  $providerId = $match.provider_match.result_json.ranked_providers[0].provider_id
  Ok "Provider Match Engine completed: ok"

  $matches = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/provider-matches" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if (-not ($matches.provider_matches | Where-Object { $_.id -eq $providerMatchId })) {
    $matches | ConvertTo-Json -Depth 30
    throw "Provider match not found in list."
  }
  Ok "List provider matches: ok"

  $matchDetail = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/provider-matches/$providerMatchId" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if ($matchDetail.provider_match.id -ne $providerMatchId) {
    $matchDetail | ConvertTo-Json -Depth 30
    throw "Provider match detail mismatch."
  }
  Ok "Provider match detail: ok"

  $quoteBody = @{ provider_id = $providerId } | ConvertTo-Json -Compress
  $quote = Invoke-RestMethod `
    -Method POST `
    -Uri "$BaseUrl/api/v1/provider-matches/$providerMatchId/quote-requests" `
    -ContentType "application/json" `
    -Headers $repairHeaders `
    -Body $quoteBody `
    -TimeoutSec 10

  if ($quote.quote_request.status -ne "estimated") {
    $quote | ConvertTo-Json -Depth 30
    throw "Quote request was not estimated."
  }
  if (-not $quote.quote_request.quote_json.total_cents) { throw "Quote missing total_cents." }
  if (-not $quote.quote_request.quote_json.platform_fee_cents) { throw "Quote missing platform_fee_cents." }
  $quoteId = $quote.quote_request.id
  Ok "Quote Engine estimated repair quote: ok"

  $quotes = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/repair-cases/$caseId/quote-requests" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if (-not ($quotes.quote_requests | Where-Object { $_.id -eq $quoteId })) {
    $quotes | ConvertTo-Json -Depth 30
    throw "Quote request not found in list."
  }
  Ok "List quote requests: ok"

  $quoteDetail = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/quote-requests/$quoteId" `
    -Headers $repairHeaders `
    -TimeoutSec 10

  if ($quoteDetail.quote_request.id -ne $quoteId) {
    $quoteDetail | ConvertTo-Json -Depth 30
    throw "Quote request detail mismatch."
  }
  Ok "Quote request detail: ok"

  $admin = Login-As "admin@reborn.local"
  $adminHeaders = AuthHeaders $admin.Token
  $events = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/domain-events?limit=160" `
    -Headers $adminHeaders `
    -TimeoutSec 10

  $eventNames = @($events.domain_events | ForEach-Object { $_.name })
  foreach ($expected in @("provider.match_requested", "provider.match_completed", "quote.requested", "quote.estimated")) {
    if ($eventNames -notcontains $expected) {
      $events | ConvertTo-Json -Depth 30
      throw "Missing domain event: $expected"
    }
    Ok "Domain event ${expected}: ok"
  }
} finally {
  if (Test-Path $tempPng) { Remove-Item $tempPng -Force }
}

Ok "Provider match and quote smoke test passed."
