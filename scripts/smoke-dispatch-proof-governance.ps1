param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 34 Fulfilment Dispatch, Shipment Tracking & Proof-of-Repair Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "fulfilment_dispatch_governance") { Fail "Health capabilities missing fulfilment_dispatch_governance." }
if ($health.capabilities -notcontains "shipment_tracking_governance") { Fail "Health capabilities missing shipment_tracking_governance." }
if ($health.capabilities -notcontains "proof_of_repair_governance") { Fail "Health capabilities missing proof_of_repair_governance." }
if ($health.capabilities -notcontains "dispatch_human_review") { Fail "Health capabilities missing dispatch_human_review." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.dispatch_governance) { Fail "Dispatch governance readiness check missing." }
Ok "Readiness includes Step 34 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/dispatch-governance" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.dispatch_governance.summary) { Fail "Dispatch governance dashboard failed." }
Ok "Dispatch governance dashboard: ok"

$policies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/dispatch-policies?status=active" -Headers $headers
if (-not $policies.success -or (CountOf $policies.dispatch_policies) -lt 1) { Fail "Dispatch policies listing failed." }
Ok "Dispatch policies: ok"

$matches = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/routing-matches?status=all&limit=30" -Headers $headers
if (-not $matches.success) { Fail "Routing matches listing failed." }
if ((CountOf $matches.routing_matches) -lt 1) {
    $geometryAssets = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/geometry-assets?status=all&limit=10" -Headers $headers
    if (-not $geometryAssets.success -or (CountOf $geometryAssets.geometry_assets) -lt 1) { Fail "No geometry assets available for routing setup." }
    $requestBody = AsJson @{
        geometry_asset_id = $geometryAssets.geometry_assets[0].id
        requested_process = "fdm_3d_printing"
        material_family = "pla_petg"
        quantity = 1
        priority = "normal"
        destination_country = "IT"
        max_lead_time_days = 7
        max_budget_cents = 4200
        routing_context = @{ smoke_test = "step34_setup"; real_capacity_booking = $false }
    }
    $routingRequest = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/routing-requests" -Headers $headers -ContentType "application/json" -Body $requestBody
    if (-not $routingRequest.success -or -not $routingRequest.routing_request.id) { Fail "Routing request setup failed." }
    $evaluation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/routing-requests/$($routingRequest.routing_request.id)/evaluate" -Headers $headers -ContentType "application/json" -Body (AsJson @{ evaluation_mode = "pilot_mock" })
    if (-not $evaluation.success -or (CountOf $evaluation.routing_evaluation.matches) -lt 1) { Fail "Routing setup evaluation produced no matches." }
    $matches = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/routing-matches?status=all&limit=30" -Headers $headers
}
$routingMatchId = $matches.routing_matches[0].id
Ok "Routing match available: ok"

$dispatchBody = AsJson @{
    routing_match_id = $routingMatchId
    fulfilment_mode = "shipped"
    carrier = "mock_carrier"
    destination_country = "IT"
    package_requirements = @{ protective_packaging = $true; pilot_label = $true }
    operator_notes = "Step 34 smoke test dispatch. Real courier booking remains deferred."
}
$dispatchResult = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/dispatches" -Headers $headers -ContentType "application/json" -Body $dispatchBody
if (-not $dispatchResult.success -or -not $dispatchResult.dispatch_result.dispatch.id) { Fail "Dispatch creation failed." }
$dispatchId = $dispatchResult.dispatch_result.dispatch.id
Ok "Dispatch creation: ok"

$dispatches = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/dispatches?status=all&limit=30" -Headers $headers
if (-not $dispatches.success -or (CountOf $dispatches.dispatches) -lt 1) { Fail "Dispatch listing failed." }
Ok "Dispatch listing: ok"

$advance = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/dispatches/$dispatchId/advance" -Headers $headers -ContentType "application/json" -Body (AsJson @{ action = "confirm_dispatch"; location = "pilot_lab"; message = "Pilot package handed to mock carrier." })
if (-not $advance.success -or $advance.dispatch.status -ne "dispatched") { Fail "Dispatch advance to dispatched failed." }
Ok "Dispatch advance: ok"

$event = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/dispatches/$dispatchId/shipment-events" -Headers $headers -ContentType "application/json" -Body (AsJson @{ event_type = "carrier_scan"; status = "in_transit"; location = "Bologna mock hub"; message = "Mock carrier scan recorded."; evidence = @{ mock_tracking = $true } })
if (-not $event.success -or -not $event.shipment_event.id) { Fail "Shipment event recording failed." }
Ok "Shipment event recording: ok"

$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/shipment-events?limit=30" -Headers $headers
if (-not $events.success -or (CountOf $events.shipment_events) -lt 1) { Fail "Shipment events listing failed." }
Ok "Shipment events listing: ok"

$proof = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/dispatches/$dispatchId/proof-of-repair" -Headers $headers -ContentType "application/json" -Body (AsJson @{ proof_type = "photo_and_notes"; summary = "Part fitted and basic functional test passed in pilot flow."; quality_score = 82; evidence = @{ photo_stub = "local://proof/smoke-step34.jpg"; functional_test_passed = $true } })
if (-not $proof.success -or -not $proof.proof_of_repair.id) { Fail "Proof-of-repair creation failed." }
$proofId = $proof.proof_of_repair.id
Ok "Proof-of-repair creation: ok"

$proofReview = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/proof-of-repair-records/$proofId/review" -Headers $headers -ContentType "application/json" -Body (AsJson @{ decision = "accepted_with_notes"; notes = "Accepted by Step 34 smoke test. Real customer acceptance remains deferred." })
if (-not $proofReview.success -or $proofReview.proof_of_repair.status -ne "accepted") { Fail "Proof-of-repair review failed." }
Ok "Proof-of-repair review: ok"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/dispatch-review-items?status=all&limit=30" -Headers $headers
if (-not $reviews.success) { Fail "Dispatch review listing failed." }
Ok "Dispatch reviews listing: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/dispatch-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.dispatch_audit_log) -lt 1) { Fail "Dispatch audit log failed." }
Ok "Dispatch audit log: ok"

Write-Host "Step 34 fulfilment dispatch, shipment tracking and proof-of-repair governance smoke test passed." -ForegroundColor Green
