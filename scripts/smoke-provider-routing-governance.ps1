param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 33 Provider Capability, Machine Profile & Fulfilment Routing Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "provider_capability_governance") { Fail "Health capabilities missing provider_capability_governance." }
if ($health.capabilities -notcontains "machine_profile_governance") { Fail "Health capabilities missing machine_profile_governance." }
if ($health.capabilities -notcontains "provider_fulfilment_routing") { Fail "Health capabilities missing provider_fulfilment_routing." }
if ($health.capabilities -notcontains "routing_human_review") { Fail "Health capabilities missing routing_human_review." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.provider_routing) { Fail "Provider routing readiness check missing." }
Ok "Readiness includes Step 33 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/provider-routing" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.provider_routing.summary) { Fail "Provider routing dashboard failed." }
Ok "Provider routing dashboard: ok"

$capabilities = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/provider-capabilities?status=active&limit=30" -Headers $headers
if (-not $capabilities.success -or (CountOf $capabilities.provider_capabilities) -lt 1) { Fail "Provider capabilities listing failed." }
Ok "Provider capabilities: ok"

$machines = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/machine-profiles?status=active&limit=30" -Headers $headers
if (-not $machines.success -or (CountOf $machines.machine_profiles) -lt 1) { Fail "Machine profiles listing failed." }
Ok "Machine profiles: ok"

$policies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/routing-policies?status=active" -Headers $headers
if (-not $policies.success -or (CountOf $policies.routing_policies) -lt 1) { Fail "Routing policies listing failed." }
Ok "Routing policies: ok"

$geometryAssets = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/geometry-assets?status=all&limit=10" -Headers $headers
if (-not $geometryAssets.success -or (CountOf $geometryAssets.geometry_assets) -lt 1) { Fail "No geometry assets available for routing smoke test." }
$geometryId = $geometryAssets.geometry_assets[0].id
Ok "Geometry asset available: ok"

$requestBody = AsJson @{
    geometry_asset_id = $geometryId
    requested_process = "fdm_3d_printing"
    material_family = "pla_petg"
    quantity = 1
    priority = "normal"
    destination_country = "IT"
    max_lead_time_days = 7
    max_budget_cents = 3800
    routing_context = @{ smoke_test = "step33"; real_capacity_booking = $false }
}
$routingRequest = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/routing-requests" -Headers $headers -ContentType "application/json" -Body $requestBody
if (-not $routingRequest.success -or -not $routingRequest.routing_request.id) { Fail "Routing request creation failed." }
Ok "Routing request creation: ok"

$evaluation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/routing-requests/$($routingRequest.routing_request.id)/evaluate" -Headers $headers -ContentType "application/json" -Body (AsJson @{ evaluation_mode = "pilot_mock" })
if (-not $evaluation.success -or -not $evaluation.routing_evaluation.routing_request.id) { Fail "Routing evaluation failed." }
if ((CountOf $evaluation.routing_evaluation.matches) -lt 1) { Fail "Routing evaluation produced no matches." }
Ok "Routing evaluation: ok"

$requests = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/routing-requests?status=all&limit=30" -Headers $headers
if (-not $requests.success -or (CountOf $requests.routing_requests) -lt 1) { Fail "Routing requests listing failed." }
Ok "Routing requests listing: ok"

$matches = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/routing-matches?status=all&limit=30" -Headers $headers
if (-not $matches.success -or (CountOf $matches.routing_matches) -lt 1) { Fail "Routing matches listing failed." }
Ok "Routing matches listing: ok"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/routing-review-items?status=active&limit=30" -Headers $headers
if (-not $reviews.success) { Fail "Routing review items listing failed." }
if ((CountOf $reviews.routing_review_items) -ge 1) {
    $reviewId = $reviews.routing_review_items[0].id
    $reviewBody = AsJson @{ decision = "approved_with_operator_notes"; notes = "Step 33 smoke test route approved for pilot handoff. Real capacity booking remains deferred." }
    $review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/routing-review-items/$reviewId/review" -Headers $headers -ContentType "application/json" -Body $reviewBody
    if (-not $review.success -or $review.routing_review_item.status -ne "closed") { Fail "Routing human review failed." }
    Ok "Routing human review: ok"
} else {
    Ok "Routing human review: no active review required."
}

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/provider-routing-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.provider_routing_audit_log) -lt 1) { Fail "Provider routing audit log failed." }
Ok "Provider routing audit log: ok"

Write-Host "Step 33 provider capability, machine profile and fulfilment routing governance smoke test passed." -ForegroundColor Green
