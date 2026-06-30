param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 41 Demo Data Room, Pilot Launch Pack & Stakeholder Feedback Loop API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
foreach ($capability in @("demo_data_room", "pilot_launch_checklist", "stakeholder_feedback_loop", "post_demo_reports", "pilot_go_no_go_decisions")) {
    if ($health.capabilities -notcontains $capability) { Fail "Health capabilities missing $capability." }
}
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.pilot_launch) { Fail "Pilot launch readiness check missing." }
Ok "Readiness includes Step 41 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-launch" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.pilot_launch.summary) { Fail "Pilot launch dashboard failed." }
if ([int]$dashboard.pilot_launch.summary.total_data_room_assets -lt 3) { Fail "Data room has too few seeded assets." }
Ok "Pilot launch dashboard: ok"

$assets = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/data-room-assets?status=all&limit=20" -Headers $headers
if (-not $assets.success -or (CountOf $assets.data_room_assets) -lt 3) { Fail "Data room assets listing failed." }
Ok "Data room assets: ok"

$newAsset = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/data-room-assets" -Headers $headers -ContentType "application/json" -Body (AsJson @{ title = "Step 41 CI Stakeholder Brief"; category = "pilot"; audience = "partner"; status = "ready"; route_hint = "docs/09-operations/STEP_41_DEMO_DATA_ROOM_PILOT_LAUNCH.md"; summary = "CI-created data room asset for stakeholder follow-up."; caveat = "Internal smoke evidence only." })
if (-not $newAsset.success -or -not $newAsset.data_room_asset.id) { Fail "Data room asset creation failed." }
Ok "Data room asset creation: ok"

$checklist = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-checklist-items?status=all&limit=30" -Headers $headers
if (-not $checklist.success -or (CountOf $checklist.pilot_checklist_items) -lt 4) { Fail "Pilot checklist listing failed." }
$checkItemId = @($checklist.pilot_checklist_items)[0].id
Ok "Pilot checklist listing: ok"

$updatedCheck = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/pilot-checklist-items/$checkItemId/status" -Headers $headers -ContentType "application/json" -Body (AsJson @{ status = "ready"; notes = "CI smoke marked checklist item ready for controlled demo." })
if (-not $updatedCheck.success -or $updatedCheck.pilot_checklist_item.status -ne "ready") { Fail "Pilot checklist status update failed." }
Ok "Pilot checklist status update: ok"

$loop = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/stakeholder-feedback-loops" -Headers $headers -ContentType "application/json" -Body (AsJson @{ audience_type = "investor"; stakeholder_name = "CI Stakeholder"; objective = "Validate Step 41 pilot launch pack and feedback loop."; notes = "Created by Step 41 smoke test." })
if (-not $loop.success -or -not $loop.stakeholder_feedback_loop.id) { Fail "Stakeholder feedback loop creation failed." }
$loopId = $loop.stakeholder_feedback_loop.id
Ok "Stakeholder feedback loop creation: ok"

$loops = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/stakeholder-feedback-loops?status=all&limit=20" -Headers $headers
if (-not $loops.success -or (CountOf $loops.stakeholder_feedback_loops) -lt 1) { Fail "Stakeholder feedback loop listing failed." }
Ok "Stakeholder feedback loops: ok"

$feedback = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/stakeholder-feedback" -Headers $headers -ContentType "application/json" -Body (AsJson @{ loop_id = $loopId; audience_type = "investor"; signal = "positive"; rating = 8; topic = "pilot_launch"; notes = "Data room and pilot workflow are clear with explicit caveats."; requested_action = "Prepare provider/legal checklist before beta." })
if (-not $feedback.success -or -not $feedback.stakeholder_feedback_item.id) { Fail "Stakeholder feedback creation failed." }
Ok "Stakeholder feedback creation: ok"

$feedbackList = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/stakeholder-feedback?loop_id=$loopId&limit=20" -Headers $headers
if (-not $feedbackList.success -or (CountOf $feedbackList.stakeholder_feedback) -lt 1) { Fail "Stakeholder feedback listing failed." }
Ok "Stakeholder feedback listing: ok"

$report = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/post-demo-reports" -Headers $headers -ContentType "application/json" -Body (AsJson @{ loop_id = $loopId; executive_summary = "Step 41 smoke post-demo report generated from structured stakeholder feedback." })
if (-not $report.success -or -not $report.post_demo_report.id) { Fail "Post-demo report creation failed." }
Ok "Post-demo report creation: ok"

$reports = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/post-demo-reports?status=all&limit=20" -Headers $headers
if (-not $reports.success -or (CountOf $reports.post_demo_reports) -lt 1) { Fail "Post-demo report listing failed." }
Ok "Post-demo reports: ok"

$decision = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/pilot-launch/evaluate" -Headers $headers -ContentType "application/json" -Body (AsJson @{ rationale = "Step 41 smoke go/no-go evaluation." })
if (-not $decision.success -or -not $decision.pilot_go_no_go_decision.id) { Fail "Pilot go/no-go evaluation failed." }
if ($decision.pilot_go_no_go_decision.decision -notin @("go", "conditional_go", "no_go")) { Fail "Pilot decision value invalid." }
Ok "Pilot go/no-go evaluation: ok"

$decisions = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-go-no-go-decisions?status=all&limit=20" -Headers $headers
if (-not $decisions.success -or (CountOf $decisions.pilot_go_no_go_decisions) -lt 1) { Fail "Pilot go/no-go listing failed." }
Ok "Pilot go/no-go decisions: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-launch-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.pilot_launch_audit_log) -lt 1) { Fail "Pilot launch audit log failed." }
Ok "Pilot launch audit log: ok"

Write-Host "Step 41 demo data room, pilot launch pack and stakeholder feedback loop smoke test passed." -ForegroundColor Green
