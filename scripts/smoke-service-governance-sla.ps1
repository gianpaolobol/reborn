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
    return ($data | ConvertTo-Json -Depth 20)
}

Write-Host "Checking Re-born Step 24 Service Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "service_governance") { Fail "Health capabilities missing service governance." }
if ($health.capabilities -notcontains "sla_evaluations") { Fail "Health capabilities missing SLA evaluations." }
if ($health.capabilities -notcontains "operational_policies") { Fail "Health capabilities missing operational policies." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.service_governance) { Fail "Service governance readiness check missing." }
Ok "Readiness includes Step 24 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/service-governance" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.service_governance) { Fail "Service governance dashboard failed." }
if (-not $dashboard.service_governance.sla_summary) { Fail "Service governance SLA summary missing." }
Ok "Service governance dashboard: ok"

$policies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/sla-policies" -Headers $headers
if (-not $policies.success -or (($policies.sla_policies | Measure-Object).Count -lt 1)) { Fail "SLA policies endpoint failed." }
Ok "SLA policies: ok"

$opsPolicies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/operational-policies" -Headers $headers
if (-not $opsPolicies.success -or (($opsPolicies.operational_policies | Measure-Object).Count -lt 1)) { Fail "Operational policies endpoint failed." }
$policyId = $opsPolicies.operational_policies[0].id
Ok "Operational policies: ok"

$attestationBody = AsJson @{ status = "acknowledged"; notes = "Step 24 smoke test attestation." }
$attestation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/operational-policies/$policyId/attest" -Headers $headers -ContentType "application/json" -Body $attestationBody
if (-not $attestation.success -or -not $attestation.policy_attestation.id) { Fail "Policy attestation failed." }
Ok "Policy attestation: ok"

$incidentBody = AsJson @{
    title = "Step 24 smoke SLA incident"
    severity = "medium"
    summary = "Smoke test creates an incident so SLA evaluation can be validated."
    impact = "No real impact; local pilot governance validation."
}
$incident = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents" -Headers $headers -ContentType "application/json" -Body $incidentBody
if (-not $incident.success -or -not $incident.incident.id) { Fail "Incident creation failed." }
Ok "Incident created: $($incident.incident.id)"

$evaluationRun = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/slas/evaluate" -Headers $headers
if (-not $evaluationRun.success -or $evaluationRun.sla_evaluation_run.evaluated_count -lt 1) { Fail "SLA evaluation run failed." }
Ok "SLA evaluation run: $($evaluationRun.sla_evaluation_run.evaluated_count) source(s)"

$evaluations = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/sla-evaluations?status=active&limit=20" -Headers $headers
if (-not $evaluations.success -or (($evaluations.sla_evaluations | Measure-Object).Count -lt 1)) { Fail "SLA evaluations listing failed." }
$evaluationId = $evaluations.sla_evaluations[0].id
Ok "SLA evaluations listing: ok"

$responseBody = AsJson @{ note = "Step 24 smoke test first response." }
$response = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/sla-evaluations/$evaluationId/response" -Headers $headers -ContentType "application/json" -Body $responseBody
if (-not $response.success -or -not $response.sla_evaluation.first_response_at) { Fail "SLA response marker failed." }
Ok "SLA response marker: ok"

$resolveBody = AsJson @{ note = "Step 24 smoke test SLA resolution." }
$resolved = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/sla-evaluations/$evaluationId/resolve" -Headers $headers -ContentType "application/json" -Body $resolveBody
if (-not $resolved.success -or $resolved.sla_evaluation.status -notin @("met", "breached")) { Fail "SLA resolution marker failed." }
Ok "SLA resolution marker: ok"

$attestations = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/policy-attestations?limit=20" -Headers $headers
if (-not $attestations.success -or (($attestations.policy_attestations | Measure-Object).Count -lt 1)) { Fail "Policy attestations listing failed." }
Ok "Policy attestations listing: ok"

$resolveIncidentBody = AsJson @{
    status = "resolved"
    component = "platform"
    message = "Step 24 smoke test resolved the incident after SLA validation."
}
$resolvedIncident = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents/$($incident.incident.id)/status" -Headers $headers -ContentType "application/json" -Body $resolveIncidentBody
if (-not $resolvedIncident.success -or $resolvedIncident.incident.status -ne "resolved") { Fail "Incident resolve failed." }
Ok "Incident resolved: ok"

Write-Host "Step 24 service governance and SLA smoke test passed." -ForegroundColor Green
