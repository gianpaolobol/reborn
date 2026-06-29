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

Write-Host "Checking Re-born Step 22 Incident Response API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "incident_response") { Fail "Health capabilities missing incident response." }
if ($health.capabilities -notcontains "status_page") { Fail "Health capabilities missing status page." }
if ($health.capabilities -notcontains "maintenance_windows") { Fail "Health capabilities missing maintenance windows." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.incident_response) { Fail "Incident response readiness check missing." }
Ok "Readiness includes Step 22 checks: $($ready.readiness.status)"

$status = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/status"
if (-not $status.success -or -not $status.status_page) { Fail "Public status page failed." }
if (-not $status.status_page.components) { Fail "Public status page components missing." }
Ok "Public status page: $($status.status_page.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$rules = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/alert-rules" -Headers $headers
if (-not $rules.success -or -not $rules.alert_rules) { Fail "Alert rules endpoint failed." }
if (($rules.alert_rules | Measure-Object).Count -lt 1) { Fail "No alert rules available." }
Ok "Alert rules: ok"

$evaluation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/alerts/evaluate" -Headers $headers
if (-not $evaluation.success -or -not $evaluation.alert_evaluation.evaluations) { Fail "Alert evaluation failed." }
Ok "Alert evaluation: ok"

$alerts = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/alerts?status=active" -Headers $headers
if (-not $alerts.success) { Fail "Alerts listing failed." }
Ok "Alerts listing: ok"

$incidentBody = AsJson @{
    title = "Smoke test incident"
    severity = "medium"
    summary = "Step 22 smoke test validates incident workflow."
    impact = "No real user impact; local pilot workflow test."
}
$incident = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents" -Headers $headers -ContentType "application/json" -Body $incidentBody
if (-not $incident.success -or -not $incident.incident.id) { Fail "Incident creation failed." }
Ok "Incident created: $($incident.incident.id)"

$monitorBody = AsJson @{
    status = "monitoring"
    component = "platform"
    message = "Smoke test moved the incident to monitoring."
}
$monitor = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents/$($incident.incident.id)/status" -Headers $headers -ContentType "application/json" -Body $monitorBody
if (-not $monitor.success -or $monitor.incident.status -ne "monitoring") { Fail "Incident monitoring update failed." }
Ok "Incident monitoring update: ok"

$resolveBody = AsJson @{
    status = "resolved"
    component = "platform"
    message = "Smoke test resolved the incident."
}
$resolved = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents/$($incident.incident.id)/status" -Headers $headers -ContentType "application/json" -Body $resolveBody
if (-not $resolved.success -or $resolved.incident.status -ne "resolved") { Fail "Incident resolve failed." }
Ok "Incident resolve: ok"

$updateBody = AsJson @{
    component = "platform"
    status = "operational"
    message = "Smoke test status update posted."
}
$statusUpdate = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/status-updates" -Headers $headers -ContentType "application/json" -Body $updateBody
if (-not $statusUpdate.success -or -not $statusUpdate.status_update.id) { Fail "Status update creation failed." }
Ok "Status update: ok"

$start = (Get-Date).ToUniversalTime().AddMinutes(5).ToString("o")
$end = (Get-Date).ToUniversalTime().AddMinutes(35).ToString("o")
$maintenanceBody = AsJson @{
    title = "Smoke test maintenance"
    status = "scheduled"
    starts_at = $start
    ends_at = $end
    reason = "Validate Step 22 maintenance workflow."
}
$maintenance = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/maintenance-windows" -Headers $headers -ContentType "application/json" -Body $maintenanceBody
if (-not $maintenance.success -or -not $maintenance.maintenance_window.id) { Fail "Maintenance creation failed." }
Ok "Maintenance scheduled: ok"

$closed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/maintenance-windows/$($maintenance.maintenance_window.id)/close" -Headers $headers
if (-not $closed.success -or $closed.maintenance_window.status -ne "completed") { Fail "Maintenance close failed." }
Ok "Maintenance closed: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/incident-response" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.incident_response.status_page) { Fail "Incident response dashboard failed." }
Ok "Incident response dashboard: ok"

Write-Host "Step 22 incident response smoke test passed." -ForegroundColor Green
