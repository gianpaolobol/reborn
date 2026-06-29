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

Info "Checking Re-born Repair Completion & Learning API at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$repair = Login-As "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token
Ok "Login repair.user: ok"

$caseBody = @{
  title = "Step 16 learning smoke case"
  description = "A real object is repaired, completed and converted into reusable learning feedback for the Knowledge Graph."
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

$acceptBody = @{ provider_notes = "Provider accepts repair responsibility for Step 16 learning smoke." } | ConvertTo-Json -Compress
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
  co2_avoided_grams = 1350
  summary = "The repaired object returned to function after provider validation and final fit check."
  repair_method = "provider_validated_replacement_part"
  material_used = "PETG"
  quality_checks = @("fit_checked", "function_checked", "visual_inspection")
  notes = "Step 16 smoke test confirms the repair outcome becomes learning data."
  evidence_attachment_ids = @()
} | ConvertTo-Json -Depth 10 -Compress

$report = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/completion-reports" -ContentType "application/json" -Headers $providerHeaders -Body $reportBody -TimeoutSec 10
if ($report.completion_report.status -ne "recorded") { $report | ConvertTo-Json -Depth 30; throw "Completion report was not recorded." }
if ($report.learning_event.event_type -ne "repair_outcome_confirmed") { $report | ConvertTo-Json -Depth 30; throw "Learning event was not recorded." }
if (-not $report.knowledge_feedback.knowledge_node_id) { $report | ConvertTo-Json -Depth 30; throw "Knowledge feedback missing node id." }
$completionReportId = $report.completion_report.id
$learningEventId = $report.learning_event.id
Ok "Completion report recorded: ok"
Ok "Learning event recorded: ok"
Ok "Knowledge Graph feedback applied: ok"

$reports = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/fulfilments/$fulfilmentId/completion-reports" -Headers $providerHeaders -TimeoutSec 10
if (@($reports.completion_reports).Count -lt 1) { $reports | ConvertTo-Json -Depth 30; throw "Completion reports list is empty." }
Ok "List completion reports: ok"

$reportDetail = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/completion-reports/$completionReportId" -Headers $providerHeaders -TimeoutSec 10
if ($reportDetail.completion_report.id -ne $completionReportId) { $reportDetail | ConvertTo-Json -Depth 30; throw "Completion report detail mismatch." }
Ok "Completion report detail: ok"

$learning = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/repair-cases/$caseId/learning-events" -Headers $providerHeaders -TimeoutSec 10
if (@($learning.learning_events).Count -lt 1) { $learning | ConvertTo-Json -Depth 30; throw "Learning events list is empty." }
Ok "List learning events: ok"

$learningDetail = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/learning-events/$learningEventId" -Headers $providerHeaders -TimeoutSec 10
if ($learningDetail.learning_event.id -ne $learningEventId) { $learningDetail | ConvertTo-Json -Depth 30; throw "Learning event detail mismatch." }
Ok "Learning event detail: ok"

$nodes = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/knowledge/nodes" -TimeoutSec 10
$feedbackNodes = @($nodes.nodes | Where-Object { $_.type -eq "repair_outcome" })
if ($feedbackNodes.Count -lt 1) { $nodes | ConvertTo-Json -Depth 30; throw "Knowledge Graph does not contain repair_outcome node." }
Ok "Knowledge Graph repair outcome node: ok"

$admin = Login-As "admin@reborn.local"
$adminHeaders = AuthHeaders $admin.Token
$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/domain-events?limit=300" -Headers $adminHeaders -TimeoutSec 10
$eventNames = @($events.domain_events | ForEach-Object { $_.name })
foreach ($expected in @("repair.completion_reported", "learning.event_recorded", "knowledge.graph_feedback_applied")) {
  if ($eventNames -notcontains $expected) {
    $events | ConvertTo-Json -Depth 30
    throw "Missing domain event: $expected"
  }
  Ok "Domain event ${expected}: ok"
}

Ok "Repair completion and learning smoke test passed."
