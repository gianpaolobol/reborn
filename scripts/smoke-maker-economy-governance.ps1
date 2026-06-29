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

Write-Host "Checking Re-born Step 29 Maker Economy Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "maker_economy") { Fail "Health capabilities missing maker economy." }
if ($health.capabilities -notcontains "model_licensing") { Fail "Health capabilities missing model licensing." }
if ($health.capabilities -notcontains "repair_bounties") { Fail "Health capabilities missing repair bounties." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.maker_economy) { Fail "Maker economy readiness check missing." }
Ok "Readiness includes Step 29 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/maker-economy" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.maker_economy.summary) { Fail "Maker economy dashboard failed." }
Ok "Maker economy dashboard: ok"

$licenses = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/model-licenses?status=all" -Headers $headers
if (-not $licenses.success -or (($licenses.model_licenses | Measure-Object).Count -lt 1)) { Fail "Model licenses listing failed." }
Ok "Model licenses listing: ok"

$profiles = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/maker-profiles?status=all&limit=20" -Headers $headers
if (-not $profiles.success -or (($profiles.maker_profiles | Measure-Object).Count -lt 1)) { Fail "Maker profiles listing failed." }
$maker = $profiles.maker_profiles | Select-Object -First 1
Ok "Maker profiles listing: ok"

$newMakerBody = AsJson @{
    maker_ref = "maker-step29-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())"
    display_name = "Step 29 Maker Profile"
    status = "onboarding"
    specialty_tags = @("reverse_engineering", "functional_parts")
    notes = "Created by Step 29 smoke test."
}
$newMaker = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/maker-profiles" -Headers $headers -ContentType "application/json" -Body $newMakerBody
if (-not $newMaker.success -or -not $newMaker.maker_profile.id) { Fail "Maker profile creation failed." }
Ok "Maker profile creation: ok"

$activateBody = AsJson @{ status = "active"; trust_tier = "verified" }
$activated = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/maker-profiles/$($newMaker.maker_profile.id)/status" -Headers $headers -ContentType "application/json" -Body $activateBody
if (-not $activated.success -or $activated.maker_profile.status -ne "active") { Fail "Maker profile activation failed." }
Ok "Maker profile activation: ok"

$modelBody = AsJson @{
    maker_profile_id = $activated.maker_profile.id
    title = "Step 29 repair latch model"
    object_category = "appliance"
    repair_use_case = "Replace a broken latch for a real repair case in the pilot journey."
    status = "in_review"
    license_key = "repair_credit_pilot"
    file_kind = "stl"
    quality_score = 70
    safety_notes = "Smoke test model asset. Pilot only."
}
$model = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/model-assets" -Headers $headers -ContentType "application/json" -Body $modelBody
if (-not $model.success -or -not $model.model_asset.id) { Fail "Model asset submission failed." }
Ok "Model asset submission: ok"

$reviewBody = AsJson @{ status = "approved"; quality_score = 91; safety_notes = "Approved by Step 29 smoke test. Pilot only." }
$approvedModel = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/model-assets/$($model.model_asset.id)/review" -Headers $headers -ContentType "application/json" -Body $reviewBody
if (-not $approvedModel.success -or $approvedModel.model_asset.status -ne "approved") { Fail "Model asset review failed." }
Ok "Model asset review: ok"

$downloadBody = AsJson @{
    model_asset_id = $approvedModel.model_asset.id
    downloader_type = "repair_user"
    downloader_ref = "step29-smoke-repair-user"
    purpose = "repair_attempt"
}
$download = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/model-downloads" -Headers $headers -ContentType "application/json" -Body $downloadBody
if (-not $download.success -or -not $download.model_download_record.download.id) { Fail "Model download recording failed." }
if ($download.model_download_record.royalty_event.credits_awarded -lt 1) { Fail "Royalty credits were not awarded." }
Ok "Model download and royalty event: ok"

$bountyBody = AsJson @{
    title = "Step 29 repair bounty"
    object_category = "home_repair"
    problem_statement = "Create a practical replacement part for a pilot repair case."
    reward_credits = 60
    priority = "high"
    source_type = "step29_smoke"
    source_ref = "bounty-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())"
}
$bounty = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/repair-bounties" -Headers $headers -ContentType "application/json" -Body $bountyBody
if (-not $bounty.success -or -not $bounty.repair_bounty.id) { Fail "Repair bounty creation failed." }
Ok "Repair bounty creation: ok"

$submissionBody = AsJson @{
    bounty_id = $bounty.repair_bounty.id
    maker_profile_id = $activated.maker_profile.id
    model_asset_id = $approvedModel.model_asset.id
    submission_notes = "Step 29 smoke test bounty submission."
}
$submission = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/bounty-submissions" -Headers $headers -ContentType "application/json" -Body $submissionBody
if (-not $submission.success -or -not $submission.bounty_submission.id) { Fail "Bounty submission failed." }
Ok "Bounty submission: ok"

$acceptBody = AsJson @{ status = "accepted"; review_notes = "Accepted by Step 29 smoke test."; awarded_credits = 60 }
$accepted = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/bounty-submissions/$($submission.bounty_submission.id)/review" -Headers $headers -ContentType "application/json" -Body $acceptBody
if (-not $accepted.success -or $accepted.bounty_submission.status -ne "accepted") { Fail "Bounty submission acceptance failed." }
if ($accepted.bounty_submission.awarded_credits -lt 1) { Fail "Bounty credits were not awarded." }
Ok "Bounty acceptance and credit award: ok"

$royalties = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/model-royalty-events?limit=20" -Headers $headers
if (-not $royalties.success -or (($royalties.model_royalty_events | Measure-Object).Count -lt 1)) { Fail "Royalty events listing failed." }
Ok "Royalty events listing: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/maker-economy-audit-log?limit=20" -Headers $headers
if (-not $audit.success -or (($audit.maker_economy_audit_log | Measure-Object).Count -lt 1)) { Fail "Maker economy audit log failed." }
Ok "Maker economy audit log: ok"

Write-Host "Step 29 maker economy governance smoke test passed." -ForegroundColor Green
