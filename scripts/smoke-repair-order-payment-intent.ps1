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

Info "Checking Re-born Repair Order & Payment Intent API at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$repair = Login-As "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token
Ok "Login repair.user: ok"

$caseBody = @{
  title = "Step 14 repair order smoke case"
  description = "A cracked plastic cover needs a provider quote, repair order and mock payment intent without real money movement."
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

$match = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/repair-cases/$caseId/provider-matches" `
  -ContentType "application/json" `
  -Headers $repairHeaders `
  -Body "{}" `
  -TimeoutSec 10

if ($match.provider_match.status -ne "completed") {
  $match | ConvertTo-Json -Depth 30
  throw "Provider match did not complete."
}
if (-not $match.provider_match.result_json.ranked_providers) { throw "Provider match missing ranked_providers." }
$providerMatchId = $match.provider_match.id
$providerId = $match.provider_match.result_json.ranked_providers[0].provider_id
Ok "Provider Match Engine completed: ok"

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
$quoteId = $quote.quote_request.id
Ok "Quote Engine estimated repair quote: ok"

$order = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/quote-requests/$quoteId/repair-orders" `
  -ContentType "application/json" `
  -Headers $repairHeaders `
  -Body "{}" `
  -TimeoutSec 10

if ($order.repair_order.status -ne "created") {
  $order | ConvertTo-Json -Depth 30
  throw "Repair order was not created."
}
if (-not $order.repair_order.total_cents) { throw "Repair order missing total_cents." }
if (-not $order.repair_order.order_json.quality_gate) { throw "Repair order missing quality gate." }
$orderId = $order.repair_order.id
Ok "Repair Order Engine created order: ok"

$orders = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/repair-cases/$caseId/repair-orders" `
  -Headers $repairHeaders `
  -TimeoutSec 10

if (-not ($orders.repair_orders | Where-Object { $_.id -eq $orderId })) {
  $orders | ConvertTo-Json -Depth 30
  throw "Repair order not found in list."
}
Ok "List repair orders: ok"

$orderDetail = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/repair-orders/$orderId" `
  -Headers $repairHeaders `
  -TimeoutSec 10

if ($orderDetail.repair_order.id -ne $orderId) {
  $orderDetail | ConvertTo-Json -Depth 30
  throw "Repair order detail mismatch."
}
Ok "Repair order detail: ok"

$intent = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/repair-orders/$orderId/payment-intents" `
  -ContentType "application/json" `
  -Headers $repairHeaders `
  -Body "{}" `
  -TimeoutSec 10

if ($intent.payment_intent.status -ne "requires_mock_confirmation") {
  $intent | ConvertTo-Json -Depth 30
  throw "Payment intent was not created in mock confirmation state."
}
if ($intent.payment_intent.amount_cents -ne $order.repair_order.total_cents) {
  $intent | ConvertTo-Json -Depth 30
  throw "Payment intent amount does not match repair order total."
}
if (-not $intent.payment_intent.client_secret) { throw "Payment intent missing client_secret." }
$paymentIntentId = $intent.payment_intent.id
Ok "Mock payment intent created: ok"

$intents = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/repair-orders/$orderId/payment-intents" `
  -Headers $repairHeaders `
  -TimeoutSec 10

if (-not ($intents.payment_intents | Where-Object { $_.id -eq $paymentIntentId })) {
  $intents | ConvertTo-Json -Depth 30
  throw "Payment intent not found in list."
}
Ok "List payment intents: ok"

$intentDetail = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/payment-intents/$paymentIntentId" `
  -Headers $repairHeaders `
  -TimeoutSec 10

if ($intentDetail.payment_intent.id -ne $paymentIntentId) {
  $intentDetail | ConvertTo-Json -Depth 30
  throw "Payment intent detail mismatch."
}
Ok "Payment intent detail: ok"

$confirmed = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/payment-intents/$paymentIntentId/confirm-mock" `
  -ContentType "application/json" `
  -Headers $repairHeaders `
  -Body "{}" `
  -TimeoutSec 10

if ($confirmed.payment_intent.status -ne "mock_authorized") {
  $confirmed | ConvertTo-Json -Depth 30
  throw "Payment intent was not mock authorized."
}
Ok "Mock payment authorization: ok"

$admin = Login-As "admin@reborn.local"
$adminHeaders = AuthHeaders $admin.Token
$events = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/domain-events?limit=200" `
  -Headers $adminHeaders `
  -TimeoutSec 10

$eventNames = @($events.domain_events | ForEach-Object { $_.name })
foreach ($expected in @("repair.order_created", "payment.intent_created", "payment.intent_mock_authorized")) {
  if ($eventNames -notcontains $expected) {
    $events | ConvertTo-Json -Depth 30
    throw "Missing domain event: $expected"
  }
  Ok "Domain event ${expected}: ok"
}

Ok "Repair order and payment intent smoke test passed."
