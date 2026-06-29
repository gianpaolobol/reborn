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

Info "Checking Re-born Admin Operations & Moderation API at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$admin = Login-As "admin@reborn.local"
$adminHeaders = AuthHeaders $admin.Token
Ok "Login admin: ok"

$policy = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ops/policies" -Headers $adminHeaders -TimeoutSec 10
if ($policy.policy.policy_version -ne "admin_operations_moderation_v1") { $policy | ConvertTo-Json -Depth 30; throw "Ops policy missing or wrong version." }
Ok "Ops policy: ok"

$providers = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/providers" -Headers $adminHeaders -TimeoutSec 10
if (@($providers.providers).Count -lt 1) { $providers | ConvertTo-Json -Depth 30; throw "No seeded providers available." }
$providerId = $providers.providers[0].id
Ok "Seed provider available: ok"

$reviewBody = @{
  source_type = "provider"
  source_id = $providerId
  provider_id = $providerId
  category = "quality"
  priority = "high"
  title = "Step 19 smoke provider review"
  description = "Ops review item created by smoke test to verify moderation workflow."
  payload = @{ source = "smoke_admin_ops_moderation"; risk = "routing_readiness" }
} | ConvertTo-Json -Depth 10 -Compress

$review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/ops/review-items" -ContentType "application/json" -Headers $adminHeaders -Body $reviewBody -TimeoutSec 10
if (-not $review.review_item.id) { $review | ConvertTo-Json -Depth 30; throw "Ops review item was not created." }
if ($review.review_item.status -ne "open") { $review | ConvertTo-Json -Depth 30; throw "Ops review item did not start open." }
$reviewId = $review.review_item.id
Ok "Ops review item created: ok"

$list = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ops/review-items?status=open" -Headers $adminHeaders -TimeoutSec 10
if (@($list.review_items | Where-Object { $_.id -eq $reviewId }).Count -lt 1) {
  $list | ConvertTo-Json -Depth 30
  throw "Open ops review item not found."
}
Ok "Ops review queue list: ok"

$assign = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/ops/review-items/$reviewId/assign" -ContentType "application/json" -Headers $adminHeaders -Body "{}" -TimeoutSec 10
if ($assign.review_item.status -ne "in_review") { $assign | ConvertTo-Json -Depth 30; throw "Ops review item was not moved to in_review." }
if (-not $assign.review_item.assigned_to) { $assign | ConvertTo-Json -Depth 30; throw "Ops review item was not assigned." }
Ok "Ops review item assigned: ok"

$actionBody = @{
  action_type = "policy_note"
  target_type = "provider"
  target_id = $providerId
  reason = "Smoke test moderation note for operational governance."
  payload = @{ note = "safe_to_continue_with_watch" }
} | ConvertTo-Json -Depth 10 -Compress

$action = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/ops/review-items/$reviewId/moderation-actions" -ContentType "application/json" -Headers $adminHeaders -Body $actionBody -TimeoutSec 10
if ($action.moderation_action.action_type -ne "policy_note") { $action | ConvertTo-Json -Depth 30; throw "Moderation action was not recorded." }
Ok "Ops moderation action recorded: ok"

$detail = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ops/review-items/$reviewId" -Headers $adminHeaders -TimeoutSec 10
if (@($detail.moderation_actions).Count -lt 1) { $detail | ConvertTo-Json -Depth 30; throw "Review item detail did not include moderation actions." }
Ok "Ops review item detail: ok"

$escalationBody = @{
  escalation_level = "ops_lead"
  reason = "Smoke test escalation before resolution."
  assigned_to = $admin.User.id
} | ConvertTo-Json -Depth 10 -Compress

$escalation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/ops/review-items/$reviewId/escalations" -ContentType "application/json" -Headers $adminHeaders -Body $escalationBody -TimeoutSec 10
if ($escalation.escalation.escalation_level -ne "ops_lead") { $escalation | ConvertTo-Json -Depth 30; throw "Ops escalation was not created." }
Ok "Ops escalation created: ok"

$escalations = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ops/escalations?status=open" -Headers $adminHeaders -TimeoutSec 10
if (@($escalations.escalations | Where-Object { $_.id -eq $escalation.escalation.id }).Count -lt 1) {
  $escalations | ConvertTo-Json -Depth 30
  throw "Open ops escalation not found."
}
Ok "Ops escalation list: ok"

$resolveBody = @{ resolution = "reviewed_and_safe_to_continue" } | ConvertTo-Json -Depth 10 -Compress
$resolved = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/ops/review-items/$reviewId/resolve" -ContentType "application/json" -Headers $adminHeaders -Body $resolveBody -TimeoutSec 10
if ($resolved.review_item.status -ne "resolved") { $resolved | ConvertTo-Json -Depth 30; throw "Ops review item was not resolved." }
Ok "Ops review item resolved: ok"

$summary = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/ops/summary" -Headers $adminHeaders -TimeoutSec 10
if ([int]$summary.summary.review_items -lt 1) { $summary | ConvertTo-Json -Depth 30; throw "Ops summary did not count review items." }
if ([int]$summary.summary.moderation_actions -lt 1) { $summary | ConvertTo-Json -Depth 30; throw "Ops summary did not count moderation actions." }
Ok "Ops summary: ok"

$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/domain-events?limit=300" -Headers $adminHeaders -TimeoutSec 10
$eventNames = @($events.domain_events | ForEach-Object { $_.name })
foreach ($expected in @("ops.review_item_created", "ops.review_item_assigned", "ops.moderation_action_recorded", "ops.escalation_created", "ops.review_item_resolved")) {
  if ($eventNames -notcontains $expected) {
    $events | ConvertTo-Json -Depth 30
    throw "Missing domain event: $expected"
  }
  Ok "Domain event ${expected}: ok"
}

Ok "Admin operations and moderation smoke test passed."
