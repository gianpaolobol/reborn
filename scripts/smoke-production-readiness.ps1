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

Write-Host "Checking Re-born Production Readiness API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "production_readiness_hardening") { Fail "Health capabilities missing production readiness." }
Ok "Health: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.database) { Fail "Readiness database check missing." }
if ($ready.readiness.checks.database.status -ne "ok") { Fail "Database readiness is not ok." }
if (-not $ready.readiness.checks.storage) { Fail "Readiness storage check missing." }
Ok "Readiness endpoint: $($ready.readiness.status)"

$policyResponse = Invoke-WebRequest -Method GET -Uri "$BaseUrl/api/v1/platform/security-policy"
if (-not $policyResponse.Headers["X-Content-Type-Options"]) { Fail "Security header X-Content-Type-Options missing." }
if (-not $policyResponse.Headers["X-Frame-Options"]) { Fail "Security header X-Frame-Options missing." }
$policy = $policyResponse.Content | ConvertFrom-Json
if (-not $policy.success -or -not $policy.security_policy) { Fail "Security policy payload missing." }
if (-not $policy.security_policy.security_headers_enabled) { Fail "Security headers are not enabled in policy." }
if (-not $policy.security_policy.rate_limit_enabled) { Fail "Rate limiting is not enabled in policy." }
Ok "Security policy and headers: ok"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$runtime = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/runtime" -Headers $headers
if (-not $runtime.success -or -not $runtime.runtime) { Fail "Runtime report failed." }
if (-not $runtime.runtime.extensions.pdo) { Fail "PDO extension is not loaded." }
if (-not $runtime.runtime.extensions.pdo_sqlite) { Fail "pdo_sqlite extension is not loaded." }
Ok "Runtime report: ok"

$checklist = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/deploy-checklist" -Headers $headers
if (-not $checklist.success -or -not $checklist.deploy_checklist.items) { Fail "Deploy checklist failed." }
Ok "Deploy checklist: ok"

$snapshot = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/readiness-snapshots" -Headers $headers
if (-not $snapshot.success -or -not $snapshot.readiness_snapshot.id) { Fail "Readiness snapshot creation failed." }
Ok "Readiness snapshot persisted: ok"

$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/domain-events?limit=20" -Headers $headers
if (-not $events.success) { Fail "Domain events endpoint failed." }
Ok "Domain events endpoint still available: ok"

Write-Host "Production readiness smoke test passed." -ForegroundColor Green
