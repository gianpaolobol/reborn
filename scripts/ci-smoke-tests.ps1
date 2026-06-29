param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$env:REBORN_BASE_URL = $BaseUrl

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$ScriptsRoot = Join-Path $Root "scripts"

$SmokeTests = @(
    "smoke-identity-access.ps1",
    "smoke-ownership-dashboards.ps1",
    "smoke-prototype-auth-ui.ps1",
    "smoke-repair-upload-recognition.ps1",
    "smoke-repair-path-decision.ps1",
    "smoke-provider-match-quote.ps1",
    "smoke-repair-order-payment-intent.ps1",
    "smoke-repair-fulfilment-workflow.ps1",
    "smoke-repair-completion-learning.ps1",
    "smoke-provider-trust-quality.ps1",
    "smoke-provider-ranking-governance.ps1",
    "smoke-admin-ops-moderation.ps1",
    "smoke-production-readiness.ps1",
    "smoke-observability-ops.ps1",
    "smoke-incident-response-status.ps1",
    "smoke-notification-escalation.ps1",
    "smoke-service-governance-sla.ps1",
    "smoke-privacy-data-governance.ps1",
    "smoke-beta-release-management.ps1",
    "smoke-partner-onboarding-governance.ps1",
    "smoke-marketplace-revenue-governance.ps1",
    "smoke-maker-economy-governance.ps1",
    "smoke-ai-pipeline-governance.ps1",
    "smoke-ai-provider-sandbox-orchestration.ps1",
    "smoke-geometry-printability-governance.ps1",
    "smoke-provider-routing-governance.ps1",
    "smoke-dispatch-proof-governance.ps1",
    "smoke-customer-care-warranty-support.ps1",
    "smoke-sustainability-impact-circularity.ps1",
    "smoke-investor-reporting-board-readiness.ps1"
)

$StartedAt = Get-Date
$Results = @()

Write-Host "Running Re-born full CI smoke suite against $BaseUrl" -ForegroundColor Cyan
Write-Host "Smoke scripts: $($SmokeTests.Count)" -ForegroundColor Cyan

foreach ($SmokeTest in $SmokeTests) {
    $ScriptPath = Join-Path $ScriptsRoot $SmokeTest
    if (-not (Test-Path $ScriptPath)) {
        throw "Smoke test script not found: $SmokeTest"
    }

    $StepStarted = Get-Date
    Write-Host ""
    Write-Host "::group::$SmokeTest"

    try {
        & $ScriptPath
        $Duration = [Math]::Round(((Get-Date) - $StepStarted).TotalSeconds, 2)
        $Results += [pscustomobject]@{
            script = $SmokeTest
            status = "passed"
            duration_seconds = $Duration
        }
        Write-Host "$SmokeTest passed in ${Duration}s" -ForegroundColor Green
    } catch {
        $Duration = [Math]::Round(((Get-Date) - $StepStarted).TotalSeconds, 2)
        $Results += [pscustomobject]@{
            script = $SmokeTest
            status = "failed"
            duration_seconds = $Duration
            error = $_.Exception.Message
        }
        Write-Host "::endgroup::"
        Write-Host "Smoke test failed: $SmokeTest" -ForegroundColor Red
        Write-Host $_.Exception.Message -ForegroundColor Red
        $Results | ConvertTo-Json -Depth 6 | Out-File -Encoding UTF8 (Join-Path $Root "storage/logs/ci-smoke-results.json")
        throw
    }

    Write-Host "::endgroup::"
}

$TotalDuration = [Math]::Round(((Get-Date) - $StartedAt).TotalSeconds, 2)
$Summary = [pscustomobject]@{
    base_url = $BaseUrl
    status = "passed"
    scripts_run = $SmokeTests.Count
    total_duration_seconds = $TotalDuration
    completed_at = (Get-Date).ToUniversalTime().ToString("o")
    results = $Results
}

$SummaryPath = Join-Path $Root "storage/logs/ci-smoke-results.json"
$Summary | ConvertTo-Json -Depth 8 | Out-File -Encoding UTF8 $SummaryPath

Write-Host ""
Write-Host "Full Re-born CI smoke suite passed in ${TotalDuration}s." -ForegroundColor Green
Write-Host "Summary written to $SummaryPath" -ForegroundColor Green
