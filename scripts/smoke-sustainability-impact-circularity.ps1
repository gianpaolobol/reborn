param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 36 Sustainability Impact, Circularity Metrics & Repair Outcome Intelligence API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "sustainability_impact") { Fail "Health capabilities missing sustainability_impact." }
if ($health.capabilities -notcontains "circularity_metrics") { Fail "Health capabilities missing circularity_metrics." }
if ($health.capabilities -notcontains "repair_outcome_intelligence") { Fail "Health capabilities missing repair_outcome_intelligence." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.sustainability_impact) { Fail "Sustainability impact readiness check missing." }
Ok "Readiness includes Step 36 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/sustainability-impact" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.sustainability_impact.summary) { Fail "Sustainability impact dashboard failed." }
Ok "Sustainability impact dashboard: ok"

$factors = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/sustainability-factors?status=active" -Headers $headers
if (-not $factors.success -or (CountOf $factors.sustainability_factors) -lt 1) { Fail "Sustainability factors listing failed." }
Ok "Sustainability factors: ok"

$acceptances = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/customer-acceptance-records?status=all&limit=10" -Headers $headers
$acceptanceId = $null
if ($acceptances.success -and (CountOf $acceptances.customer_acceptance_records) -gt 0) {
    $acceptanceId = $acceptances.customer_acceptance_records[0].id
}

$impactBody = AsJson @{ acceptance_record_id = $acceptanceId; category = "plastic_part"; object_weight_kg = 0.45; estimated_lifespan_months = 24; evidence = @{ smoke_test = "step36"; acceptance_available = [bool]$acceptanceId } }
$impact = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/repair-impact-records" -Headers $headers -ContentType "application/json" -Body $impactBody
if (-not $impact.success -or -not $impact.repair_impact_record.id) { Fail "Repair impact record creation failed." }
$impactId = $impact.repair_impact_record.id
Ok "Repair impact record creation: ok"

$calculated = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/repair-impact-records/$impactId/calculate" -Headers $headers -ContentType "application/json" -Body (AsJson @{ category = "plastic_part"; object_weight_kg = 0.45; estimated_lifespan_months = 24 })
if (-not $calculated.success -or $calculated.repair_impact_record.status -notin @("calculated", "needs_review")) { Fail "Repair impact calculation failed." }
if ([double]$calculated.repair_impact_record.co2e_avoided_kg -le 0) { Fail "Repair impact CO2e estimate was not calculated." }
Ok "Repair impact calculation: ok"

$records = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/repair-impact-records?status=all&limit=30" -Headers $headers
if (-not $records.success -or (CountOf $records.repair_impact_records) -lt 1) { Fail "Repair impact listing failed." }
Ok "Repair impact listing: ok"

$snapshot = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/circularity-snapshots" -Headers $headers -ContentType "application/json" -Body (AsJson @{ scope = "pilot"; status = "draft" })
if (-not $snapshot.success -or -not $snapshot.circularity_snapshot.id) { Fail "Circularity snapshot creation failed." }
Ok "Circularity snapshot creation: ok"

$snapshots = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/circularity-snapshots?limit=30" -Headers $headers
if (-not $snapshots.success -or (CountOf $snapshots.circularity_snapshots) -lt 1) { Fail "Circularity snapshot listing failed." }
Ok "Circularity snapshot listing: ok"

$insightEval = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/repair-outcome-insights/evaluate" -Headers $headers -ContentType "application/json" -Body (AsJson @{ source = "smoke-step36" })
if (-not $insightEval.success -or -not $insightEval.repair_outcome_insight_evaluation.created_insights) { Fail "Repair outcome insight evaluation failed." }
Ok "Repair outcome insight evaluation: ok"

$insights = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/repair-outcome-insights?status=all&limit=30" -Headers $headers
if (-not $insights.success -or (CountOf $insights.repair_outcome_insights) -lt 1) { Fail "Repair outcome insight listing failed." }
Ok "Repair outcome insights listing: ok"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/impact-review-items?status=all&limit=30" -Headers $headers
if (-not $reviews.success) { Fail "Impact review listing failed." }
if ((CountOf $reviews.impact_review_items) -gt 0) {
    $reviewId = $reviews.impact_review_items[0].id
    $review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/impact-review-items/$reviewId/review" -Headers $headers -ContentType "application/json" -Body (AsJson @{ decision = "accepted_for_internal_reporting"; notes = "Smoke test review: internal pilot estimate only." })
    if (-not $review.success -or $review.impact_review_item.status -ne "resolved") { Fail "Impact review resolution failed." }
    Ok "Impact review resolution: ok"
} else {
    Ok "Impact review resolution: skipped, no review items open"
}

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/sustainability-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.sustainability_audit_log) -lt 1) { Fail "Sustainability audit log failed." }
Ok "Sustainability audit log: ok"

Write-Host "Step 36 sustainability impact, circularity metrics and repair outcome intelligence smoke test passed." -ForegroundColor Green
