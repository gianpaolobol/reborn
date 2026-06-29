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

Write-Host "Checking Re-born Step 27 Partner Onboarding Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "partner_onboarding") { Fail "Health capabilities missing partner onboarding." }
if ($health.capabilities -notcontains "partner_readiness") { Fail "Health capabilities missing partner readiness." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.partner_onboarding) { Fail "Partner onboarding readiness check missing." }
Ok "Readiness includes Step 27 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/partner-onboarding" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.partner_onboarding.summary) { Fail "Partner onboarding dashboard failed." }
Ok "Partner onboarding dashboard: ok"

$partners = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/partners?status=all&limit=20" -Headers $headers
if (-not $partners.success -or (($partners.partners | Measure-Object).Count -lt 1)) { Fail "Partners listing failed." }
Ok "Partners listing: ok"

$partnerEmail = "step27-partner-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())@reborn.local"
$partnerBody = AsJson @{
    name = "Step 27 Smoke Partner"
    partner_type = "provider"
    tier = "pilot"
    status = "onboarding"
    contact_name = "Smoke Partner Lead"
    contact_email = $partnerEmail
    notes = "Created by Step 27 smoke test."
}
$created = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/partners" -Headers $headers -ContentType "application/json" -Body $partnerBody
if (-not $created.success -or -not $created.partner.id) { Fail "Partner creation failed." }
$partner = $created.partner
Ok "Partner created: $($partner.partner_code)"

$tasks = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/partner-tasks?status=all&limit=50" -Headers $headers
if (-not $tasks.success -or (($tasks.partner_tasks | Measure-Object).Count -lt 1)) { Fail "Partner tasks listing failed." }
$partnerTasks = $tasks.partner_tasks | Where-Object { $_.partner_id -eq $partner.id }
if (($partnerTasks | Measure-Object).Count -lt 1) { Fail "Created partner has no onboarding tasks." }
foreach ($task in $partnerTasks) {
    $taskBody = AsJson @{ status = "completed"; evidence = "Completed by Step 27 smoke test." }
    $updatedTask = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/partner-tasks/$($task.id)/status" -Headers $headers -ContentType "application/json" -Body $taskBody
    if (-not $updatedTask.success -or $updatedTask.partner_task.status -ne "completed") { Fail "Partner task update failed." }
}
Ok "Partner tasks completed: ok"

$agreements = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/partner-agreements?status=all&limit=50" -Headers $headers
if (-not $agreements.success) { Fail "Partner agreements listing failed." }
$agreement = $agreements.partner_agreements | Where-Object { $_.partner_id -eq $partner.id } | Select-Object -First 1
if (-not $agreement) { Fail "Created partner has no agreement." }
$agreementBody = AsJson @{ status = "accepted"; notes = "Accepted by Step 27 smoke test as local pilot evidence." }
$acceptedAgreement = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/partner-agreements/$($agreement.id)/status" -Headers $headers -ContentType "application/json" -Body $agreementBody
if (-not $acceptedAgreement.success -or $acceptedAgreement.partner_agreement.status -ne "accepted") { Fail "Partner agreement acceptance failed." }
Ok "Partner agreement accepted: ok"

$integrationBody = AsJson @{
    integration_type = "manual"
    name = "Step 27 manual quote workflow"
    environment = "local_pilot"
    scopes = @("quote_request", "fulfilment_status")
    notes = "Created by Step 27 smoke test."
}
$integration = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/partners/$($partner.id)/integrations" -Headers $headers -ContentType "application/json" -Body $integrationBody
if (-not $integration.success -or -not $integration.partner_integration.id) { Fail "Partner integration creation failed." }
$integrationStatusBody = AsJson @{ status = "testing"; notes = "Checked by Step 27 smoke test." }
$updatedIntegration = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/partner-integrations/$($integration.partner_integration.id)/status" -Headers $headers -ContentType "application/json" -Body $integrationStatusBody
if (-not $updatedIntegration.success -or $updatedIntegration.partner_integration.status -ne "testing") { Fail "Partner integration status update failed." }
Ok "Partner integration testing: ok"

$review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/partners/$($partner.id)/readiness/evaluate" -Headers $headers
if (-not $review.success -or -not $review.partner_readiness_review.id) { Fail "Partner readiness evaluation failed." }
if ($review.partner_readiness_review.status -notin @("ready_for_pilot", "conditional", "blocked")) { Fail "Unexpected partner readiness status." }
Ok "Partner readiness evaluation: $($review.partner_readiness_review.status)"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/partner-readiness-reviews?limit=20" -Headers $headers
if (-not $reviews.success -or (($reviews.partner_readiness_reviews | Measure-Object).Count -lt 1)) { Fail "Partner readiness review listing failed." }
Ok "Partner readiness reviews listing: ok"

Write-Host "Step 27 partner onboarding governance smoke test passed." -ForegroundColor Green
