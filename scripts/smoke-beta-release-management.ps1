param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) {
    Write-Host $message -ForegroundColor Green
}

function Fail($message) {
    throw $message
}

function AsJson($data) {
    return ($data | ConvertTo-Json -Depth 30)
}

Write-Host "Checking Re-born Step 26 Beta Release Management API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "release_management") { Fail "Health capabilities missing release management." }
if ($health.capabilities -notcontains "feature_flags") { Fail "Health capabilities missing feature flags." }
if ($health.capabilities -notcontains "beta_readiness") { Fail "Health capabilities missing beta readiness." }
if ($health.capabilities -notcontains "pilot_cohorts") { Fail "Health capabilities missing pilot cohorts." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.release_management) { Fail "Release management readiness check missing." }
Ok "Readiness includes Step 26 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$backup = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/backups" -Headers $headers
if (-not $backup.success -or -not $backup.backup.id) { Fail "Backup creation failed." }
Ok "Backup evidence created: ok"

$snapshot = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/readiness-snapshots" -Headers $headers
if (-not $snapshot.success -or -not $snapshot.readiness_snapshot.id) { Fail "Readiness snapshot creation failed." }
Ok "Readiness snapshot evidence created: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/release-management" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.release_management) { Fail "Release management dashboard failed." }
if (-not $dashboard.release_management.summary) { Fail "Release management summary missing." }
Ok "Release management dashboard: ok"

$beta = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/beta-readiness" -Headers $headers
if (-not $beta.success -or -not $beta.beta_readiness.gates) { Fail "Beta readiness endpoint failed." }
Ok "Beta readiness gates: $($beta.beta_readiness.required_gate_count) required"

$flags = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/feature-flags" -Headers $headers
if (-not $flags.success -or (($flags.feature_flags | Measure-Object).Count -lt 1)) { Fail "Feature flags endpoint failed." }
$publicStatusFlag = $flags.feature_flags | Where-Object { $_.flag_key -eq "public_status_page" } | Select-Object -First 1
if (-not $publicStatusFlag) { $publicStatusFlag = $flags.feature_flags[0] }
Ok "Feature flags listing: ok"

$flagBody = AsJson @{ status = "beta"; rollout_percentage = 50; default_state = $false; notes = "Step 26 smoke test flag update." }
$updatedFlag = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/feature-flags/$($publicStatusFlag.id)" -Headers $headers -ContentType "application/json" -Body $flagBody
if (-not $updatedFlag.success -or $updatedFlag.feature_flag.status -ne "beta") { Fail "Feature flag update failed." }
Ok "Feature flag update: ok"

$releases = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/releases?status=active&limit=20" -Headers $headers
if (-not $releases.success) { Fail "Releases endpoint failed." }
$release = $releases.releases | Select-Object -First 1
if (-not $release) {
    $releaseBody = AsJson @{
        title = "Step 26 smoke release"
        version = "0.26.0-smoke"
        risk_level = "medium"
        target_environment = "local_pilot"
        release_type = "beta_readiness"
        notes = "Created by Step 26 smoke test."
    }
    $createdRelease = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/releases" -Headers $headers -ContentType "application/json" -Body $releaseBody
    if (-not $createdRelease.success -or -not $createdRelease.release.id) { Fail "Release creation failed." }
    $release = $createdRelease.release
}
Ok "Release available: $($release.release_code)"

$gateRun = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/releases/$($release.id)/evaluate-gates" -Headers $headers
if (-not $gateRun.success -or $gateRun.release_gate_evaluation.gate_count -lt 1) { Fail "Release gate evaluation failed." }
Ok "Release gates evaluated: $($gateRun.release_gate_evaluation.gate_count) gate(s)"

$gates = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/releases/$($release.id)/gates" -Headers $headers
if (-not $gates.success -or (($gates.release_gates | Measure-Object).Count -lt 1)) { Fail "Release gates listing failed." }
Ok "Release gates listing: ok"

$decisionBody = AsJson @{ decision = "approve"; rationale = "Step 26 smoke test approval after local gate evaluation." }
$decision = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/releases/$($release.id)/decision" -Headers $headers -ContentType "application/json" -Body $decisionBody
if (-not $decision.success -or $decision.release_decision.release.status -ne "approved") { Fail "Release approval decision failed." }
Ok "Release decision recorded: ok"

$cohorts = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-cohorts" -Headers $headers
if (-not $cohorts.success -or (($cohorts.pilot_cohorts | Measure-Object).Count -lt 1)) { Fail "Pilot cohorts endpoint failed." }
$cohort = $cohorts.pilot_cohorts[0]
Ok "Pilot cohorts listing: ok"

$cohortBody = AsJson @{ status = "recruiting"; notes = "Step 26 smoke test moved cohort to recruiting." }
$updatedCohort = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/pilot-cohorts/$($cohort.id)" -Headers $headers -ContentType "application/json" -Body $cohortBody
if (-not $updatedCohort.success -or $updatedCohort.pilot_cohort.status -ne "recruiting") { Fail "Pilot cohort update failed." }
Ok "Pilot cohort update: ok"

$participantEmail = "step26-pilot-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())@reborn.local"
$participantBody = AsJson @{
    cohort_id = $cohort.id
    display_name = "Step 26 Pilot Participant"
    email = $participantEmail
    role = $cohort.target_persona
    status = "invited"
    consent_status = "pending"
    onboarding_state = "invited"
    notes = "Created by Step 26 smoke test."
}
$participant = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/pilot-participants" -Headers $headers -ContentType "application/json" -Body $participantBody
if (-not $participant.success -or -not $participant.pilot_participant.id) { Fail "Pilot participant creation failed." }
Ok "Pilot participant created: ok"

$participantUpdateBody = AsJson @{ status = "active"; consent_status = "granted"; onboarding_state = "ready_for_feedback"; notes = "Activated by Step 26 smoke test." }
$updatedParticipant = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/pilot-participants/$($participant.pilot_participant.id)" -Headers $headers -ContentType "application/json" -Body $participantUpdateBody
if (-not $updatedParticipant.success -or $updatedParticipant.pilot_participant.status -ne "active" -or $updatedParticipant.pilot_participant.consent_status -ne "granted") { Fail "Pilot participant update failed." }
Ok "Pilot participant activated: ok"

$participants = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/pilot-participants?status=all&limit=20" -Headers $headers
if (-not $participants.success -or (($participants.pilot_participants | Measure-Object).Count -lt 1)) { Fail "Pilot participants listing failed." }
Ok "Pilot participants listing: ok"

$decisions = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/release-decisions?limit=20" -Headers $headers
if (-not $decisions.success -or (($decisions.release_decisions | Measure-Object).Count -lt 1)) { Fail "Release decisions listing failed." }
Ok "Release decisions listing: ok"

Write-Host "Step 26 beta release management and pilot readiness smoke test passed." -ForegroundColor Green
