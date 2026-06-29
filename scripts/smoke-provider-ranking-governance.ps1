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

Info "Checking Re-born Provider Ranking & Marketplace Governance API at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
if ($health.status -ne "ok") { throw "Health check did not return ok." }
Ok "Health: $($health.status)"

$admin = Login-As "admin@reborn.local"
$adminHeaders = AuthHeaders $admin.Token
Ok "Login admin: ok"

$providers = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/providers" -Headers $adminHeaders -TimeoutSec 10
if (@($providers.providers).Count -lt 1) { $providers | ConvertTo-Json -Depth 30; throw "No seeded providers available." }
$providerId = $providers.providers[0].id
Ok "Seed providers available: ok"

$policy = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/governance/policies" -Headers $adminHeaders -TimeoutSec 10
if ($policy.policy.policy_version -ne "marketplace_governance_v1") { $policy | ConvertTo-Json -Depth 30; throw "Governance policy missing or wrong version." }
Ok "Governance policy: ok"

$snapshot = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/governance/ranking-snapshots" -ContentType "application/json" -Headers $adminHeaders -Body "{}" -TimeoutSec 10
if (-not $snapshot.ranking_snapshot.id) { $snapshot | ConvertTo-Json -Depth 30; throw "Ranking snapshot was not created." }
if (@($snapshot.provider_rankings).Count -lt 1) { $snapshot | ConvertTo-Json -Depth 30; throw "Ranking snapshot contains no provider rankings." }
Ok "Provider ranking snapshot created: ok"

$actionBody = @{
  action_type = "watchlist"
  severity = "medium"
  score_adjustment = -10
  reason = "Step 18 smoke test governance review before broader routing."
  notes = "Provider remains usable but should be monitored by ops."
} | ConvertTo-Json -Depth 10 -Compress

$action = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/providers/$providerId/governance-actions" -ContentType "application/json" -Headers $adminHeaders -Body $actionBody -TimeoutSec 10
if ($action.governance_action.action_type -ne "watchlist") { $action | ConvertTo-Json -Depth 30; throw "Governance action was not recorded as watchlist." }
if ($action.governance_action.provider_id -ne $providerId) { $action | ConvertTo-Json -Depth 30; throw "Governance action provider mismatch." }
Ok "Provider governance action recorded: ok"

$providerActions = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/providers/$providerId/governance-actions?active_only=1" -Headers $adminHeaders -TimeoutSec 10
if (@($providerActions.governance_actions | Where-Object { $_.id -eq $action.governance_action.id }).Count -lt 1) {
  $providerActions | ConvertTo-Json -Depth 30
  throw "Active provider governance action not found."
}
Ok "Provider active governance actions: ok"

$refreshed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/governance/ranking-snapshots" -ContentType "application/json" -Headers $adminHeaders -Body "{}" -TimeoutSec 10
$rankedProvider = @($refreshed.provider_rankings | Where-Object { $_.provider_id -eq $providerId })[0]
if (-not $rankedProvider) { $refreshed | ConvertTo-Json -Depth 30; throw "Governed provider not present in refreshed ranking." }
if ($rankedProvider.routing_status -ne "watchlist") { $rankedProvider | ConvertTo-Json -Depth 30; throw "Governed provider was not routed to watchlist." }
if ([double]$rankedProvider.governance_adjustment -ge 0) { $rankedProvider | ConvertTo-Json -Depth 30; throw "Governance adjustment was not applied." }
Ok "Governed provider ranking refreshed: ok"

$latest = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/governance/ranking-snapshots/latest" -Headers $adminHeaders -TimeoutSec 10
if ($latest.ranking_snapshot.id -ne $refreshed.ranking_snapshot.id) { $latest | ConvertTo-Json -Depth 30; throw "Latest governance ranking snapshot mismatch." }
Ok "Latest ranking snapshot: ok"

$rankings = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/governance/provider-rankings" -Headers $adminHeaders -TimeoutSec 10
if (@($rankings.provider_rankings).Count -lt 1) { $rankings | ConvertTo-Json -Depth 30; throw "Provider rankings list is empty." }
Ok "Provider rankings list: ok"

$summary = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/governance/summary" -Headers $adminHeaders -TimeoutSec 10
if ([int]$summary.summary.active_governance_actions -lt 1) { $summary | ConvertTo-Json -Depth 30; throw "Governance summary did not count active actions." }
Ok "Governance summary: ok"

$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/domain-events?limit=250" -Headers $adminHeaders -TimeoutSec 10
$eventNames = @($events.domain_events | ForEach-Object { $_.name })
foreach ($expected in @("governance.provider_action_recorded", "governance.provider_ranking_snapshot_created")) {
  if ($eventNames -notcontains $expected) {
    $events | ConvertTo-Json -Depth 30
    throw "Missing domain event: $expected"
  }
  Ok "Domain event ${expected}: ok"
}

Ok "Provider ranking and marketplace governance smoke test passed."
