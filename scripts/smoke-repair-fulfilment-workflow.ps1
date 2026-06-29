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

Info "Checking Re-born Repair Fulfilment Workflow API at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$repair = Login-As "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token
Ok "Login repair.user: ok"

$caseBody = @{
  title = "Step 15 fulfilment smoke case"
  description = "A repaired object must move from quote and mock payment into provider acceptance, production status and completion."
  category = "consumer_electronics"
} | ConvertTo-Json -Compress

$created = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -ContentType "application/json" -Headers $repairHeaders -Body $caseBody -TimeoutSec 10
if (-not $created.repair_case.id) { $created | ConvertTo-Json -Depth 20; throw "Repair case creation failed." }
$caseId = $created.repair_case.id
Ok "Create repair case: ok"

$match = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases/$caseId/provider-matches" -ContentType "application/json" -Headers $repairHeaders -Body "{}" -TimeoutSec 10
if ($match.provider_match.status -ne "completed") { $match | ConvertTo-Json -Depth 30; throw "Provider match did not complete." }
$providerMatchId = $match.provider_match.id
$providerId = $match.provider_match.result_json.ranked_providers[0].provider_id
Ok "Provider match completed: ok"

$quoteBody = @{ provider_id = $providerId } | ConvertTo-Json -Compress
$quote = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/provider-matches/$providerMatchId/quote-requests" -ContentType "application/json" -Headers $repairHeaders -Body $quoteBody -TimeoutSec 10
if ($quote.quote_request.status -ne "estimated") { $quote | ConvertTo-Json -Depth 30; throw "Quote was not estimated." }
$quoteId = $quote.quote_request.id
Ok "Quote estimated: ok"

$order = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/quote-requests/$quoteId/repair-orders" -ContentType "application/json" -Headers $repairHeaders -Body "{}" -TimeoutSec 10
if ($order.repair_order.status -ne "created") { $order | ConvertTo-Json -Depth 30; throw "Repair order was not created." }
$orderId = $order.repair_order.id
Ok "Repair order created: ok"

$intent = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-orders/$orderId/payment-intents" -ContentType "application/json" -Headers $repairHeaders -Body "{}" -TimeoutSec 10
if ($intent.payment_intent.status -ne "requires_mock_confirmation") { $intent | ConvertTo-Json -Depth 30; throw "Payment intent was not created." }
$paymentIntentId = $intent.payment_intent.id
Ok "Mock payment intent created: ok"

$confirmed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/payment-intents/$paymentIntentId/confirm-mock" -ContentType "application/json" -Headers $repairHeaders -Body "{}" -TimeoutSec 10
if ($confirmed.payment_intent.status -ne "mock_authorized") { $confirmed | ConvertTo-Json -Depth 30; throw "Payment intent was not mock authorized." }
Ok "Mock payment authorized: ok"

$fulfilment = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-orders/$orderId/fulfilments" -ContentType "application/json" -Headers $repairHeaders -Body "{}" -TimeoutSec 10
if ($fulfilment.fulfilment.status -ne "awaiting_provider_acceptance") { $fulfilment | ConvertTo-Json -Depth 30; throw "Fulfilment was not created in awaiting_provider_acceptance state." }
if (-not $fulfilment.fulfilment.timeline_json) { throw "Fulfilment missing timeline_json." }
$fulfilmentId = $fulfilment.fulfilment.id
Ok "Fulfilment workflow created: ok"

$list = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/repair-orders/$orderId/fulfilments" -Headers $repairHeaders -TimeoutSec 10
if (-not ($list.fulfilments | Where-Object { $_.id -eq $fulfilmentId })) { $list | ConvertTo-Json -Depth 30; throw "Fulfilment not found in order list." }
Ok "List fulfilments: ok"

$detail = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId" -Headers $repairHeaders -TimeoutSec 10
if ($detail.fulfilment.id -ne $fulfilmentId) { $detail | ConvertTo-Json -Depth 30; throw "Fulfilment detail mismatch." }
Ok "Fulfilment detail: ok"

$provider = Login-As "provider@reborn.local"
$providerHeaders = AuthHeaders $provider.Token
Ok "Login provider: ok"

$acceptBody = @{ provider_notes = "Provider accepts dimensional validation, material checks and repair outcome responsibility." } | ConvertTo-Json -Compress
$accepted = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/accept-provider" -ContentType "application/json" -Headers $providerHeaders -Body $acceptBody -TimeoutSec 10
if ($accepted.fulfilment.status -ne "accepted") { $accepted | ConvertTo-Json -Depth 30; throw "Provider acceptance failed." }
if (-not $accepted.fulfilment.accepted_by) { throw "Fulfilment missing accepted_by." }
Ok "Provider accepted fulfilment: ok"

$progressBody = @{ status = "in_progress"; note = "Provider started repair validation and production preparation." } | ConvertTo-Json -Compress
$progress = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/status" -ContentType "application/json" -Headers $providerHeaders -Body $progressBody -TimeoutSec 10
if ($progress.fulfilment.status -ne "in_progress") { $progress | ConvertTo-Json -Depth 30; throw "Fulfilment did not move to in_progress." }
Ok "Fulfilment in progress: ok"

$completeBody = @{ status = "completed"; note = "Repair completed and object returned to function." } | ConvertTo-Json -Compress
$completed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/status" -ContentType "application/json" -Headers $providerHeaders -Body $completeBody -TimeoutSec 10
if ($completed.fulfilment.status -ne "completed") { $completed | ConvertTo-Json -Depth 30; throw "Fulfilment did not complete." }
if (-not $completed.fulfilment.completed_at) { throw "Fulfilment missing completed_at." }
Ok "Fulfilment completed: ok"

$admin = Login-As "admin@reborn.local"
$adminHeaders = AuthHeaders $admin.Token
$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/domain-events?limit=250" -Headers $adminHeaders -TimeoutSec 10
$eventNames = @($events.domain_events | ForEach-Object { $_.name })
foreach ($expected in @("repair.fulfilment_requested", "repair.fulfilment_provider_accepted", "repair.fulfilment_status_updated")) {
  if ($eventNames -notcontains $expected) {
    $events | ConvertTo-Json -Depth 30
    throw "Missing domain event: $expected"
  }
  Ok "Domain event ${expected}: ok"
}

Ok "Repair fulfilment workflow smoke test passed."
