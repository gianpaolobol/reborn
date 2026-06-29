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

Write-Host "Checking Re-born Step 31 AI Provider Sandbox & Job Orchestration API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "ai_provider_adapter_sandbox") { Fail "Health capabilities missing ai_provider_adapter_sandbox." }
if ($health.capabilities -notcontains "ai_job_orchestration") { Fail "Health capabilities missing ai_job_orchestration." }
if ($health.capabilities -notcontains "ai_provider_cost_ledger") { Fail "Health capabilities missing ai_provider_cost_ledger." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.ai_provider_sandbox) { Fail "AI provider sandbox readiness check missing." }
Ok "Readiness includes Step 31 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-provider-sandbox" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.ai_provider_sandbox.summary) { Fail "AI provider sandbox dashboard failed." }
Ok "AI provider sandbox dashboard: ok"

$adapters = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-provider-adapters?status=all" -Headers $headers
if (-not $adapters.success -or (($adapters.ai_provider_adapters | Measure-Object).Count -lt 1)) { Fail "AI provider adapters listing failed." }
Ok "AI provider adapters listing: ok"

$healthCheck = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-provider-adapters/health-check" -Headers $headers
if (-not $healthCheck.success -or -not $healthCheck.ai_adapter_health_check.results) { Fail "AI adapter health check failed." }
Ok "AI adapter health check: ok"

$jobBody = AsJson @{
    adapter_key = "mock_meshy_image_to_3d"
    job_type = "image_to_3d_model"
    input_summary = "Step 31 smoke test image-to-3D sandbox job. No external provider call."
    priority = 38
    estimated_cost_cents = 180
}
$job = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-orchestration-jobs" -Headers $headers -ContentType "application/json" -Body $jobBody
if (-not $job.success -or -not $job.ai_orchestration_job.id) { Fail "AI orchestration job creation failed." }
Ok "AI orchestration job creation: ok"

$runningBody = AsJson @{ status = "running" }
$running = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-orchestration-jobs/$($job.ai_orchestration_job.id)/advance" -Headers $headers -ContentType "application/json" -Body $runningBody
if (-not $running.success -or $running.ai_orchestration_job.status -ne "running") { Fail "AI orchestration job run transition failed." }
Ok "AI orchestration job running transition: ok"

$succeededBody = AsJson @{ status = "succeeded"; provider_response_ref = "step31-smoke-mock-response"; actual_cost_cents = 180 }
$succeeded = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-orchestration-jobs/$($job.ai_orchestration_job.id)/advance" -Headers $headers -ContentType "application/json" -Body $succeededBody
if (-not $succeeded.success -or $succeeded.ai_orchestration_job.status -ne "succeeded") { Fail "AI orchestration job success transition failed." }
Ok "AI orchestration job success transition: ok"

$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-job-events?limit=30" -Headers $headers
if (-not $events.success -or (($events.ai_job_events | Measure-Object).Count -lt 1)) { Fail "AI job events listing failed." }
Ok "AI job events listing: ok"

$artifacts = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-artifact-stubs?limit=30" -Headers $headers
if (-not $artifacts.success -or (($artifacts.ai_artifact_stubs | Measure-Object).Count -lt 1)) { Fail "AI artifact stubs listing failed." }
Ok "AI artifact stubs listing: ok"

$ledger = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-provider-cost-ledger?limit=30" -Headers $headers
if (-not $ledger.success -or (($ledger.ai_provider_cost_ledger | Measure-Object).Count -lt 1)) { Fail "AI provider cost ledger failed." }
Ok "AI provider cost ledger: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-provider-sandbox-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (($audit.ai_provider_sandbox_audit_log | Measure-Object).Count -lt 1)) { Fail "AI provider sandbox audit log failed." }
Ok "AI provider sandbox audit log: ok"

Write-Host "Step 31 AI provider sandbox and job orchestration smoke test passed." -ForegroundColor Green
