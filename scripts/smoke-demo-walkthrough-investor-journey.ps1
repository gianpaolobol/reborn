param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 40 Demo Mode, Guided Repair Journey & Investor Walkthrough API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
foreach ($capability in @("demo_mode", "guided_repair_journey", "investor_walkthrough", "demo_sessions", "demo_feedback", "demo_readiness_reviews")) {
    if ($health.capabilities -notcontains $capability) { Fail "Health capabilities missing $capability." }
}
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.demo_walkthrough) { Fail "Demo walkthrough readiness check missing." }
Ok "Readiness includes Step 40 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-walkthrough" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.demo_walkthrough.summary) { Fail "Demo walkthrough dashboard failed." }
if ([int]$dashboard.demo_walkthrough.summary.active_steps -lt 6) { Fail "Demo walkthrough has too few active steps." }
Ok "Demo walkthrough dashboard: ok"

$modes = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-modes?status=active" -Headers $headers
if (-not $modes.success -or (CountOf $modes.demo_modes) -lt 1) { Fail "Demo mode listing failed." }
$modeId = $modes.demo_modes[0].id
Ok "Demo modes: ok"

$steps = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-walkthrough-steps?mode_id=$modeId&status=active" -Headers $headers
if (-not $steps.success -or (CountOf $steps.demo_walkthrough_steps) -lt 6) { Fail "Demo walkthrough steps listing failed." }
$firstStepKey = $steps.demo_walkthrough_steps[0].step_key
Ok "Demo walkthrough steps: ok"

$session = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/demo-sessions" -Headers $headers -ContentType "application/json" -Body (AsJson @{ mode_id = $modeId; audience = "investor"; presenter_name = "CI Smoke"; status = "running"; notes = "Step 40 guided demo smoke session." })
if (-not $session.success -or -not $session.demo_session.id) { Fail "Demo session creation failed." }
$sessionId = $session.demo_session.id
if ($session.demo_session.status -notin @("running", "draft")) { Fail "Demo session status invalid." }
Ok "Demo session creation: ok"

$advanced = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/demo-sessions/$sessionId/advance" -Headers $headers -ContentType "application/json" -Body (AsJson @{ step_key = $firstStepKey; outcome = "step_completed"; notes = "CI smoke advanced the first walkthrough step." })
if (-not $advanced.success -or $advanced.demo_session.status -notin @("running", "completed")) { Fail "Demo session advance failed." }
Ok "Demo session advance: ok"

$sessions = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-sessions?status=all&limit=20" -Headers $headers
if (-not $sessions.success -or (CountOf $sessions.demo_sessions) -lt 1) { Fail "Demo session listing failed." }
Ok "Demo session listing: ok"

$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-session-events?session_id=$sessionId&limit=20" -Headers $headers
if (-not $events.success -or (CountOf $events.demo_session_events) -lt 1) { Fail "Demo session events failed." }
Ok "Demo session events: ok"

$feedback = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/demo-feedback" -Headers $headers -ContentType "application/json" -Body (AsJson @{ session_id = $sessionId; audience_type = "investor"; rating = 8; signal = "positive"; notes = "Guided demo is clear with explicit caveats."; next_action = "Prepare partner walkthrough." })
if (-not $feedback.success -or -not $feedback.demo_feedback_record.id) { Fail "Demo feedback creation failed." }
Ok "Demo feedback creation: ok"

$feedbackList = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-feedback?session_id=$sessionId&limit=20" -Headers $headers
if (-not $feedbackList.success -or (CountOf $feedbackList.demo_feedback) -lt 1) { Fail "Demo feedback listing failed." }
Ok "Demo feedback listing: ok"

$review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/demo-readiness/evaluate" -Headers $headers -ContentType "application/json" -Body (AsJson @{ notes = "Step 40 smoke readiness evaluation." })
if (-not $review.success -or -not $review.demo_readiness_review.id) { Fail "Demo readiness evaluation failed." }
$reviewId = $review.demo_readiness_review.id
if ([int]$review.demo_readiness_review.score -lt 0) { Fail "Demo readiness score invalid." }
Ok "Demo readiness evaluation: ok"

$reviewed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/demo-readiness-reviews/$reviewId/review" -Headers $headers -ContentType "application/json" -Body (AsJson @{ decision = "reviewed_with_caveats"; notes = "Smoke review: demo is local/pilot only." })
if (-not $reviewed.success -or $reviewed.demo_readiness_review.status -notin @("reviewed", "approved", "needs_work", "archived")) { Fail "Demo readiness review failed." }
Ok "Demo readiness review: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/demo-walkthrough-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.demo_walkthrough_audit_log) -lt 1) { Fail "Demo walkthrough audit log failed." }
Ok "Demo walkthrough audit log: ok"

Write-Host "Step 40 demo mode, guided repair journey and investor walkthrough smoke test passed." -ForegroundColor Green
