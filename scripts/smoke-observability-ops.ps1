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

Write-Host "Checking Re-born Step 21 Observability API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "observability_dashboard") { Fail "Health capabilities missing observability dashboard." }
if ($health.capabilities -notcontains "backup_automation") { Fail "Health capabilities missing backup automation." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.observability) { Fail "Observability readiness check missing." }
if (-not $ready.readiness.checks.backup) { Fail "Backup readiness check missing." }
Ok "Readiness includes Step 21 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$observability = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/observability" -Headers $headers
if (-not $observability.success -or -not $observability.observability) { Fail "Observability dashboard failed." }
if (-not $observability.observability.http) { Fail "Observability HTTP summary missing." }
if (-not $observability.observability.backup) { Fail "Observability backup status missing." }
Ok "Observability dashboard: ok"

$metrics = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/http-metrics?limit=10" -Headers $headers
if (-not $metrics.success -or -not $metrics.http_metrics.summary) { Fail "HTTP metrics failed." }
if ($metrics.http_metrics.summary.total_requests -lt 1) { Fail "HTTP metrics did not record requests." }
Ok "HTTP metrics: ok"

$backup = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/backups" -Headers $headers
if (-not $backup.success -or -not $backup.backup.id) { Fail "Backup creation failed." }
if ($backup.backup.status -ne "completed") { Fail "Backup did not complete: $($backup.backup.status)" }
Ok "Backup created: $($backup.backup.backup_file)"

$backups = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/backups" -Headers $headers
if (-not $backups.success -or -not $backups.backups) { Fail "Backup listing failed." }
Ok "Backup listing: ok"

$snapshots = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/readiness-snapshots" -Headers $headers
if (-not $snapshots.success) { Fail "Readiness snapshot history failed." }
Ok "Readiness history: ok"

$logs = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/logs?limit=20" -Headers $headers
if (-not $logs.success -or -not $logs.logs) { Fail "Log viewer failed." }
Ok "Log viewer: ok"

$runbook = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/deployment-runbook" -Headers $headers
if (-not $runbook.success -or -not $runbook.deployment_runbook.phases) { Fail "Deployment runbook failed." }
Ok "Deployment runbook: ok"

$smoke = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/smoke-tests-summary" -Headers $headers
if (-not $smoke.success -or -not $smoke.smoke_tests.run_order) { Fail "Smoke test summary failed." }
Ok "Smoke test summary: ok"

Write-Host "Step 21 observability smoke test passed." -ForegroundColor Green
