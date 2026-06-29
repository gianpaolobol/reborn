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

Info "Checking Re-born Trust, Reputation & Provider Quality API at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$repair = Login-As "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token
Ok "Login repair.user: ok"

$caseBody = @{
  title = "Step 17 trust smoke case"
  description = "A completed repair is converted into provider trust, reputation and quality scoring."
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
if ($fulfilment.fulfilment.status -ne "awaiting_provider_acceptance") { $fulfilment | ConvertTo-Json -Depth 30; throw "Fulfilment was not created." }
$fulfilmentId = $fulfilment.fulfilment.id
Ok "Fulfilment workflow created: ok"

$provider = Login-As "provider@reborn.local"
$providerHeaders = AuthHeaders $provider.Token
Ok "Login provider: ok"

$acceptBody = @{ provider_notes = "Provider accepts repair responsibility for Step 17 trust smoke." } | ConvertTo-Json -Compress
$accepted = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/accept-provider" -ContentType "application/json" -Headers $providerHeaders -Body $acceptBody -TimeoutSec 10
if ($accepted.fulfilment.status -ne "accepted") { $accepted | ConvertTo-Json -Depth 30; throw "Provider did not accept fulfilment." }
Ok "Provider accepted fulfilment: ok"

$progressBody = @{ status = "in_progress"; note = "Provider started repair work." } | ConvertTo-Json -Compress
$progress = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/status" -ContentType "application/json" -Headers $providerHeaders -Body $progressBody -TimeoutSec 10
if ($progress.fulfilment.status -ne "in_progress") { $progress | ConvertTo-Json -Depth 30; throw "Fulfilment did not move to in_progress." }
Ok "Fulfilment in progress: ok"

$completeBody = @{ status = "completed"; note = "Repair completed and object returned to function." } | ConvertTo-Json -Compress
$completed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/status" -ContentType "application/json" -Headers $providerHeaders -Body $completeBody -TimeoutSec 10
if ($completed.fulfilment.status -ne "completed") { $completed | ConvertTo-Json -Depth 30; throw "Fulfilment did not complete." }
Ok "Fulfilment completed: ok"

$reportBody = @{
  outcome_status = "successful"
  functional_result = "object_returned_to_function"
  customer_confirmed = $true
  object_saved = $true
  co2_avoided_grams = 1420
  summary = "The repaired object returned to function and is ready for trust scoring."
  repair_method = "provider_validated_replacement_part"
  material_used = "PETG"
  quality_checks = @("fit_checked", "function_checked", "visual_inspection")
  notes = "Step 17 smoke test prepares quality score input."
  evidence_attachment_ids = @()
} | ConvertTo-Json -Depth 10 -Compress

$report = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/completion-reports" -ContentType "application/json" -Headers $providerHeaders -Body $reportBody -TimeoutSec 10
if ($report.completion_report.status -ne "recorded") { $report | ConvertTo-Json -Depth 30; throw "Completion report was not recorded." }
$completionReportId = $report.completion_report.id
Ok "Completion report recorded: ok"

$reviewBody = @{
  rating_overall = 5
  rating_quality = 5
  rating_communication = 4
  rating_timeliness = 5
  would_recommend = $true
  issue_resolved = $true
  comment = "Repair user confirms the provider returned the object to function with strong quality and timing."
} | ConvertTo-Json -Depth 10 -Compress

$trust = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/completion-reports/$completionReportId/trust-reviews" -ContentType "application/json" -Headers $repairHeaders -Body $reviewBody -TimeoutSec 10
if ($trust.trust_review.status -ne "published") { $trust | ConvertTo-Json -Depth 30; throw "Trust review was not published." }
if ($trust.quality_score.provider_id -ne $providerId) { $trust | ConvertTo-Json -Depth 30; throw "Quality score provider mismatch." }
if ([double]$trust.quality_score.overall_score -le 0) { $trust | ConvertTo-Json -Depth 30; throw "Quality score did not increase." }
$trustReviewId = $trust.trust_review.id
Ok "Trust review recorded: ok"
Ok "Provider quality score updated: ok"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/completion-reports/$completionReportId/trust-reviews" -Headers $repairHeaders -TimeoutSec 10
if (@($reviews.trust_reviews).Count -lt 1) { $reviews | ConvertTo-Json -Depth 30; throw "Trust reviews list is empty." }
Ok "List trust reviews: ok"

$score = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/providers/$providerId/quality-score" -Headers $repairHeaders -TimeoutSec 10
if ($score.quality_score.provider_id -ne $providerId) { $score | ConvertTo-Json -Depth 30; throw "Provider quality score detail mismatch." }
if ($score.quality_score.trust_tier -eq "unrated") { $score | ConvertTo-Json -Depth 30; throw "Provider quality score did not assign a trust tier." }
Ok "Provider quality score detail: ok"

$scores = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/provider-quality-scores" -Headers $repairHeaders -TimeoutSec 10
if (@($scores.quality_scores).Count -lt 1) { $scores | ConvertTo-Json -Depth 30; throw "Provider quality score list is empty." }
Ok "List provider quality scores: ok"

$signals = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/providers/$providerId/trust-signals" -Headers $repairHeaders -TimeoutSec 10
if (@($signals.trust_signals).Count -lt 1) { $signals | ConvertTo-Json -Depth 30; throw "Provider trust signals list is empty." }
Ok "Provider trust signals: ok"

$admin = Login-As "admin@reborn.local"
$adminHeaders = AuthHeaders $admin.Token
$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/domain-events?limit=400" -Headers $adminHeaders -TimeoutSec 10
$eventNames = @($events.domain_events | ForEach-Object { $_.name })
foreach ($expected in @("trust.review_recorded", "provider.trust_signal_recorded", "provider.quality_score_updated")) {
  if ($eventNames -notcontains $expected) {
    $events | ConvertTo-Json -Depth 30
    throw "Missing domain event: $expected"
  }
  Ok "Domain event ${expected}: ok"
}

Ok "Provider trust and quality smoke test passed."
