param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 42 Public Pilot Demo, Partner Intake & Real-World Validation API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
foreach ($capability in @("public_pilot_demo", "partner_provider_maker_intake", "external_pilot_intake", "real_world_validation_cases", "stakeholder_lead_scoring")) {
    if ($health.capabilities -notcontains $capability) { Fail "Health capabilities missing $capability." }
}
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.public_pilot) { Fail "Public pilot readiness check missing." }
if ([int]$ready.readiness.checks.public_pilot.active_public_pages -lt 2) { Fail "Public pilot active page seed missing." }
Ok "Readiness includes Step 42 checks: $($ready.readiness.status)"

$publicDemo = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/public-pilot-demo"
if (-not $publicDemo.success -or -not $publicDemo.public_pilot_demo.pages) { Fail "Public pilot demo endpoint failed." }
if ((CountOf $publicDemo.public_pilot_demo.pages) -lt 2) { Fail "Public demo pages missing." }
Ok "Public pilot demo endpoint: ok"

$publicSubmissionBody = AsJson @{
    stakeholder_type = "provider"
    organization_name = "CI Public Pilot Provider"
    contact_name = "CI Pilot Contact"
    email = "ci-public-pilot@example.test"
    country = "Italy"
    city = "Bologna"
    capabilities = @("fdm_printing", "petg", "local_pickup", "proof_of_repair")
    repair_categories = @("small_appliances", "plastic_components")
    motivation = "CI smoke submission for a controlled public pilot. We can validate provider routing, local fulfilment, proof-of-repair and customer acceptance workflows before any production rollout."
}
$publicSubmission = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/public-pilot-intake" -ContentType "application/json" -Body $publicSubmissionBody
if (-not $publicSubmission.success -or -not $publicSubmission.pilot_intake_submission.id) { Fail "Public pilot intake submission failed." }
$submissionId = $publicSubmission.pilot_intake_submission.id
if ([int]$publicSubmission.pilot_intake_submission.pilot_fit_score -lt 60) { Fail "Public intake lead score too low for smoke scenario." }
Ok "Public pilot intake submission: ok"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/public-pilot" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.public_pilot.summary) { Fail "Public pilot dashboard failed." }
if ([int]$dashboard.public_pilot.summary.active_public_pages -lt 2) { Fail "Public pilot dashboard seed pages missing." }
Ok "Public pilot dashboard: ok"

$pages = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/public-pilot-pages?status=all&limit=20" -Headers $headers
if (-not $pages.success -or (CountOf $pages.public_pilot_pages) -lt 2) { Fail "Public pilot pages listing failed." }
Ok "Public pilot pages: ok"

$submissions = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-intake-submissions?status=all&stakeholder_type=all&limit=30" -Headers $headers
if (-not $submissions.success -or (CountOf $submissions.pilot_intake_submissions) -lt 1) { Fail "Pilot intake submissions listing failed." }
Ok "Pilot intake submissions listing: ok"

$review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/pilot-intake-submissions/$submissionId/review" -Headers $headers -ContentType "application/json" -Body (AsJson @{ status = "shortlisted"; triage_notes = "CI smoke shortlisted this public pilot lead for real-world validation." })
if (-not $review.success -or $review.pilot_intake_submission.status -ne "shortlisted") { Fail "Pilot intake review failed." }
Ok "Pilot intake review: ok"

$fromIntake = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/pilot-intake-submissions/$submissionId/validation-case" -Headers $headers -ContentType "application/json" -Body (AsJson @{ object_name = "CI shortlisted repair object"; problem_statement = "Validate the full repair journey from external intake through provider routing, proof-of-repair and customer acceptance."; success_criteria = @("Repair path can be governed", "Provider route is reviewable", "Proof-of-repair can be captured") })
if (-not $fromIntake.success -or -not $fromIntake.real_world_validation_case.id) { Fail "Validation case from intake failed." }
$caseId = $fromIntake.real_world_validation_case.id
Ok "Validation case from intake: ok"

$newCase = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/real-world-validation-cases" -Headers $headers -ContentType "application/json" -Body (AsJson @{ repair_category = "consumer_parts"; object_name = "CI drawer runner clip"; problem_statement = "A small broken plastic component needs governed diagnosis, provider routing and repair acceptance evidence."; success_criteria = @("Functional movement restored", "Evidence is auditable"); evidence = @("ci_smoke_created"); pilot_fit_score = 77; governance_risk = "medium" })
if (-not $newCase.success -or -not $newCase.real_world_validation_case.id) { Fail "Validation case creation failed." }
Ok "Validation case creation: ok"

$caseUpdate = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/real-world-validation-cases/$caseId/status" -Headers $headers -ContentType "application/json" -Body (AsJson @{ status = "approved" })
if (-not $caseUpdate.success -or $caseUpdate.real_world_validation_case.status -ne "approved") { Fail "Validation case status update failed." }
Ok "Validation case status update: ok"

$cases = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/real-world-validation-cases?status=all&limit=30" -Headers $headers
if (-not $cases.success -or (CountOf $cases.real_world_validation_cases) -lt 1) { Fail "Validation cases listing failed." }
Ok "Validation cases listing: ok"

$scores = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-stakeholder-lead-scores?limit=30" -Headers $headers
if (-not $scores.success -or (CountOf $scores.pilot_stakeholder_lead_scores) -lt 1) { Fail "Lead score listing failed." }
Ok "Stakeholder lead scores: ok"

$evaluation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/public-pilot/evaluate" -Headers $headers -ContentType "application/json" -Body (AsJson @{})
if (-not $evaluation.success -or -not $evaluation.public_pilot_evaluation.recommendation) { Fail "Public pilot evaluation failed." }
if ($evaluation.public_pilot_evaluation.recommendation -notin @("ready_for_controlled_public_pilot", "continue_private_recruiting", "hold_and_collect_more_evidence")) { Fail "Public pilot recommendation invalid." }
Ok "Public pilot evaluation: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/public-pilot-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.public_pilot_audit_log) -lt 1) { Fail "Public pilot audit log failed." }
Ok "Public pilot audit log: ok"

Write-Host "Step 42 public pilot demo, external intake and real-world validation smoke test passed." -ForegroundColor Green
