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

Write-Host "Checking Re-born Step 25 Privacy & Data Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "privacy_governance") { Fail "Health capabilities missing privacy governance." }
if ($health.capabilities -notcontains "consent_records") { Fail "Health capabilities missing consent records." }
if ($health.capabilities -notcontains "data_subject_requests") { Fail "Health capabilities missing data subject requests." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.privacy_governance) { Fail "Privacy governance readiness check missing." }
Ok "Readiness includes Step 25 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/privacy-governance" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.privacy_governance) { Fail "Privacy governance dashboard failed." }
if (-not $dashboard.privacy_governance.summary) { Fail "Privacy governance summary missing." }
Ok "Privacy governance dashboard: ok"

$notices = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/privacy-notices" -Headers $headers
if (-not $notices.success -or (($notices.privacy_notices | Measure-Object).Count -lt 1)) { Fail "Privacy notices endpoint failed." }
$notice = $notices.privacy_notices | Where-Object { $_.code -eq "REPAIR-INTAKE-PRIVACY" } | Select-Object -First 1
if (-not $notice) { $notice = $notices.privacy_notices[0] }
Ok "Privacy notices: ok"

$consentBody = AsJson @{
    subject_email = "repair.user@reborn.local"
    notice_id = $notice.id
    consent_type = "privacy_notice_acknowledged"
    status = "granted"
    source = "step25_smoke_test"
    metadata = @{ smoke = $true; note = "Step 25 smoke test consent." }
}
$consent = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/consent-records" -Headers $headers -ContentType "application/json" -Body $consentBody
if (-not $consent.success -or -not $consent.consent_record.id -or $consent.consent_record.status -ne "granted") { Fail "Consent creation failed." }
Ok "Consent record created: ok"

$withdrawBody = AsJson @{ note = "Step 25 smoke test withdrawal." }
$withdrawn = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/consent-records/$($consent.consent_record.id)/withdraw" -Headers $headers -ContentType "application/json" -Body $withdrawBody
if (-not $withdrawn.success -or $withdrawn.consent_record.status -ne "withdrawn") { Fail "Consent withdrawal failed." }
Ok "Consent withdrawal: ok"

$processing = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/data-processing-records" -Headers $headers
if (-not $processing.success -or (($processing.data_processing_records | Measure-Object).Count -lt 1)) { Fail "Data processing records endpoint failed." }
Ok "Data processing records: ok"

$retentionRules = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/retention-rules" -Headers $headers
if (-not $retentionRules.success -or (($retentionRules.retention_rules | Measure-Object).Count -lt 1)) { Fail "Retention rules endpoint failed." }
Ok "Retention rules: ok"

$retentionRun = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/retention/evaluate" -Headers $headers
if (-not $retentionRun.success -or $retentionRun.retention_evaluation_run.evaluated_count -lt 1) { Fail "Retention evaluation failed." }
Ok "Retention dry-run evaluated: $($retentionRun.retention_evaluation_run.evaluated_count) rule(s)"

$retentionEvaluations = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/retention-evaluations?limit=20" -Headers $headers
if (-not $retentionEvaluations.success -or (($retentionEvaluations.retention_evaluations | Measure-Object).Count -lt 1)) { Fail "Retention evaluations listing failed." }
Ok "Retention evaluations listing: ok"

$dsrBody = AsJson @{
    request_type = "access"
    subject_email = "repair.user@reborn.local"
    priority = "normal"
    description = "Step 25 smoke test subject access request."
}
$dsr = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/data-subject-requests" -Headers $headers -ContentType "application/json" -Body $dsrBody
if (-not $dsr.success -or -not $dsr.data_subject_request.id -or $dsr.data_subject_request.status -ne "open") { Fail "Data subject request creation failed." }
Ok "Data subject request created: ok"

$export = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/data-subject-requests/$($dsr.data_subject_request.id)/export" -Headers $headers
if (-not $export.success -or -not $export.data_export.id -or -not $export.data_export.payload_summary) { Fail "Data export generation failed." }
Ok "Data export generated: ok"

$exports = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/data-exports?limit=20" -Headers $headers
if (-not $exports.success -or (($exports.data_exports | Measure-Object).Count -lt 1)) { Fail "Data exports listing failed." }
Ok "Data exports listing: ok"

$resolveBody = AsJson @{ status = "fulfilled"; resolution_notes = "Step 25 smoke test fulfilled after export generation." }
$resolved = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/data-subject-requests/$($dsr.data_subject_request.id)/resolve" -Headers $headers -ContentType "application/json" -Body $resolveBody
if (-not $resolved.success -or $resolved.data_subject_request.status -ne "fulfilled") { Fail "Data subject request resolution failed." }
Ok "Data subject request resolved: ok"

Write-Host "Step 25 privacy, consent and data governance smoke test passed." -ForegroundColor Green
