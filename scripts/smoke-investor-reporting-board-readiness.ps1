param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 37 Investor Demo, KPI Narrative & Board Reporting Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
foreach ($capability in @("investor_reporting", "investor_kpi_snapshots", "demo_narrative_sections", "board_reports", "investor_demo_readiness")) {
    if ($health.capabilities -notcontains $capability) { Fail "Health capabilities missing $capability." }
}
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.investor_reporting) { Fail "Investor reporting readiness check missing." }
Ok "Readiness includes Step 37 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/investor-reporting" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.investor_reporting.summary) { Fail "Investor reporting dashboard failed." }
Ok "Investor reporting dashboard: ok"

$kpis = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/investor-kpi-definitions?status=active" -Headers $headers
if (-not $kpis.success -or (CountOf $kpis.investor_kpi_definitions) -lt 1) { Fail "Investor KPI definitions listing failed." }
Ok "Investor KPI definitions: ok"

$snapshot = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/investor-kpi-snapshots" -Headers $headers -ContentType "application/json" -Body (AsJson @{ scope = "investor_demo"; status = "draft" })
if (-not $snapshot.success -or -not $snapshot.investor_kpi_snapshot.id) { Fail "Investor KPI snapshot creation failed." }
$snapshotId = $snapshot.investor_kpi_snapshot.id
if ([int]$snapshot.investor_kpi_snapshot.demo_score -lt 0) { Fail "Investor KPI snapshot demo score invalid." }
Ok "Investor KPI snapshot creation: ok"

$snapshots = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/investor-kpi-snapshots?status=all&limit=20" -Headers $headers
if (-not $snapshots.success -or (CountOf $snapshots.investor_kpi_snapshots) -lt 1) { Fail "Investor KPI snapshot listing failed." }
Ok "Investor KPI snapshot listing: ok"

$sections = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-narrative-sections?status=active" -Headers $headers
if (-not $sections.success -or (CountOf $sections.demo_narrative_sections) -lt 1) { Fail "Demo narrative sections listing failed." }
Ok "Demo narrative sections: ok"

$report = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/board-reports" -Headers $headers -ContentType "application/json" -Body (AsJson @{ kpi_snapshot_id = $snapshotId; title = "Step 37 Smoke Board Report"; period_label = "pilot" })
if (-not $report.success -or -not $report.board_report.id) { Fail "Board report creation failed." }
$reportId = $report.board_report.id
Ok "Board report creation: ok"

$reports = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/board-reports?status=all&limit=20" -Headers $headers
if (-not $reports.success -or (CountOf $reports.board_reports) -lt 1) { Fail "Board report listing failed." }
Ok "Board report listing: ok"

$reportSections = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/board-reports/$reportId/sections" -Headers $headers
if (-not $reportSections.success -or (CountOf $reportSections.board_report_sections) -lt 1) { Fail "Board report sections failed." }
Ok "Board report sections: ok"

$evidence = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/board-report-evidence?board_report_id=$reportId&limit=20" -Headers $headers
if (-not $evidence.success -or (CountOf $evidence.board_report_evidence) -lt 1) { Fail "Board report evidence failed." }
Ok "Board report evidence: ok"

$published = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/board-reports/$reportId/publish" -Headers $headers -ContentType "application/json" -Body (AsJson @{ status = "published" })
if (-not $published.success -or $published.board_report.status -ne "published") { Fail "Board report publish failed." }
Ok "Board report publish: ok"

$review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/investor-demo-readiness/evaluate" -Headers $headers -ContentType "application/json" -Body (AsJson @{ notes = "Smoke Step 37 readiness evaluation." })
if (-not $review.success -or -not $review.investor_demo_readiness_review.id) { Fail "Investor demo readiness evaluation failed." }
$reviewId = $review.investor_demo_readiness_review.id
Ok "Investor demo readiness evaluation: ok"

$reviewed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/investor-demo-readiness-reviews/$reviewId/review" -Headers $headers -ContentType "application/json" -Body (AsJson @{ decision = "reviewed_with_caveats"; notes = "Smoke review: demo evidence is local/pilot only." })
if (-not $reviewed.success -or $reviewed.investor_demo_readiness_review.status -notin @("reviewed", "approved", "needs_work", "archived")) { Fail "Investor demo readiness review failed." }
Ok "Investor demo readiness review: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/investor-reporting-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.investor_reporting_audit_log) -lt 1) { Fail "Investor reporting audit log failed." }
Ok "Investor reporting audit log: ok"

Write-Host "Step 37 investor demo, KPI narrative and board reporting governance smoke test passed." -ForegroundColor Green
