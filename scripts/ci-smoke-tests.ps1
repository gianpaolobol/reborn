param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$CiSmokeSuiteVersion = "STEP40_CI_SMOKE_SUITE_WITH_DEMO_WALKTHROUGH_V1"
Write-Host "CI smoke script version: $CiSmokeSuiteVersion" -ForegroundColor Magenta

$env:REBORN_BASE_URL = $BaseUrl

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$ScriptsRoot = Join-Path $Root "scripts"
$LogsRoot = Join-Path $Root "storage/logs"
New-Item -ItemType Directory -Force -Path $LogsRoot | Out-Null

$SummaryPath = Join-Path $LogsRoot "ci-smoke-results.json"
$ServerLog = Join-Path $LogsRoot "ci-php-server.log"

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
    "smoke-investor-reporting-board-readiness.ps1",
    "smoke-demo-walkthrough-investor-journey.ps1"
)

function Escape-GitHubCommandValue([string]$Value) {
    if ($null -eq $Value) { return "" }
    return $Value.Replace('%', '%25').Replace("`r", '%0D').Replace("`n", '%0A')
}

function Write-CiError([string]$Title, [string]$Message, [string]$File = "scripts/ci-smoke-tests.ps1") {
    $safeTitle = Escape-GitHubCommandValue $Title
    $safeMessage = Escape-GitHubCommandValue $Message
    Write-Host "::error file=$File,title=$safeTitle::$safeMessage"
}

function Write-CiNotice([string]$Title, [string]$Message) {
    $safeTitle = Escape-GitHubCommandValue $Title
    $safeMessage = Escape-GitHubCommandValue $Message
    Write-Host "::notice title=$safeTitle::$safeMessage"
}

function Try-JsonGet([string]$Uri, [hashtable]$Headers = @{}) {
    try {
        if ($Headers.Count -gt 0) {
            return Invoke-RestMethod -Method GET -Uri $Uri -Headers $Headers -TimeoutSec 15
        }
        return Invoke-RestMethod -Method GET -Uri $Uri -TimeoutSec 15
    } catch {
        return [pscustomobject]@{ error = $_.Exception.Message; uri = $Uri }
    }
}

function Save-FailureDiagnostics([string]$FailedScript, [object]$ErrorRecord, [object[]]$Results) {
    $diagnostics = [ordered]@{
        failed_script = $FailedScript
        failed_at = (Get-Date).ToUniversalTime().ToString("o")
        error_message = $ErrorRecord.Exception.Message
        position = $ErrorRecord.InvocationInfo.PositionMessage
        script_stack_trace = $ErrorRecord.ScriptStackTrace
        readiness = Try-JsonGet "$BaseUrl/api/ready"
        health = Try-JsonGet "$BaseUrl/api/health"
        server_log_tail = @()
        results_so_far = $Results
    }

    if (Test-Path $ServerLog) {
        $diagnostics.server_log_tail = Get-Content $ServerLog -Tail 200
    }

    $diagnosticsPath = Join-Path $LogsRoot "ci-failure-diagnostics.json"
    $diagnostics | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $diagnosticsPath

    $summary = [ordered]@{
        base_url = $BaseUrl
        status = "failed"
        failed_script = $FailedScript
        failed_at = $diagnostics.failed_at
        error = $ErrorRecord.Exception.Message
        results = $Results
        diagnostics_file = $diagnosticsPath
    }
    $summary | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $SummaryPath

    if ($env:GITHUB_STEP_SUMMARY) {
        Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "## Re-born smoke suite failed"
        Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
        Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "Failed script: ``$FailedScript``"
        Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
        Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "Error: ``$($ErrorRecord.Exception.Message)``"
        Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
        Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "Diagnostics written to ``storage/logs/ci-failure-diagnostics.json`` and uploaded as an artifact."
    }
}

$StartedAt = Get-Date
$Results = @()

Write-Host "Running Re-born full CI smoke suite against $BaseUrl" -ForegroundColor Cyan
Write-Host "Smoke scripts: $($SmokeTests.Count)" -ForegroundColor Cyan

function Invoke-CiPhpScript([string]$Script) {
    $scriptPath = Join-Path $Root $Script
    if (-not (Test-Path $scriptPath)) {
        throw "Required CI helper not found: $Script"
    }

    Write-Host "Running CI helper: $Script" -ForegroundColor Cyan
    $output = & php $scriptPath 2>&1
    $exitCode = $LASTEXITCODE
    if ($output) { $output | ForEach-Object { Write-Host $_ } }
    if ($exitCode -ne 0) {
        throw "CI helper failed with exit code ${exitCode}: $Script"
    }
}

function Assert-CiAdminLogin() {
    $loginBody = @{ email = "admin@reborn.local"; password = "password" } | ConvertTo-Json -Compress
    try {
        $login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody -TimeoutSec 20
    } catch {
        $failure = [ordered]@{
            checked_at = (Get-Date).ToUniversalTime().ToString("o")
            stage = "ci-smoke-suite-auth-guard"
            base_url = $BaseUrl
            error = $_.Exception.Message
            position = $_.InvocationInfo.PositionMessage
            demo_credentials_verification = (& php (Join-Path $Root "scripts/verify-demo-credentials.php") 2>&1) -join "`n"
            ready = Try-JsonGet "$BaseUrl/api/ready"
            health = Try-JsonGet "$BaseUrl/api/health"
            server_log_tail = @()
        }
        if (Test-Path $ServerLog) {
            $failure.server_log_tail = Get-Content $ServerLog -Tail 200
        }
        $failurePath = Join-Path $LogsRoot "ci-smoke-auth-guard-failure.json"
        $failure | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $failurePath
        throw "CI auth guard failed before smoke suite. See storage/logs/ci-smoke-auth-guard-failure.json"
    }

    $token = $null
    if ($login.token -and $login.token.access_token) { $token = $login.token.access_token }
    if (-not $token -and $login.access_token) { $token = $login.access_token }
    if (-not $token -and $login.data -and $login.data.token -and $login.data.token.access_token) { $token = $login.data.token.access_token }
    if (-not $token -and $login.data -and $login.data.access_token) { $token = $login.data.access_token }

    if (-not $token) {
        throw "CI auth guard login succeeded but did not return an access token."
    }

    Write-Host "CI auth guard login passed." -ForegroundColor Green
}

# Harden against stale persisted demo hashes or partially applied seed fixes.
# This script is CI-specific, so the guard runs unconditionally before the first smoke login.
$IdentitySmokePath = Join-Path $ScriptsRoot "smoke-identity-access.ps1"
$IdentitySmokeText = Get-Content -Raw -Path $IdentitySmokePath
if ($IdentitySmokeText -notmatch "STEP38_IDENTITY_SMOKE_GUARD_V3") {
    throw "Outdated scripts/smoke-identity-access.ps1 detected. Expected marker STEP38_IDENTITY_SMOKE_GUARD_V3. The workflow is not running the patched files. Re-apply the latest Step 38 zip, commit, push, and confirm the Actions run uses the newest commit SHA."
}

Invoke-CiPhpScript "scripts/reset-demo-credentials.php"
Invoke-CiPhpScript "scripts/verify-demo-credentials.php"
Assert-CiAdminLogin


foreach ($SmokeTest in $SmokeTests) {
    $ScriptPath = Join-Path $ScriptsRoot $SmokeTest
    if (-not (Test-Path $ScriptPath)) {
        Write-CiError "Missing smoke test script" "Smoke test script not found: $SmokeTest" "scripts/ci-smoke-tests.ps1"
        throw "Smoke test script not found: $SmokeTest"
    }

    $StepStarted = Get-Date
    Write-Host ""
    Write-Host "::group::$SmokeTest"

    try {
        # Keep REBORN_BASE_URL for older smoke scripts and also pass -BaseUrl to newer scripts that define a param block.
        $scriptText = Get-Content -Raw -Path $ScriptPath
        if ($scriptText.TrimStart().StartsWith('param(')) {
            & $ScriptPath -BaseUrl $BaseUrl
        } else {
            & $ScriptPath
        }

        $Duration = [Math]::Round(((Get-Date) - $StepStarted).TotalSeconds, 2)
        $Results += [pscustomobject]@{
            script = $SmokeTest
            status = "passed"
            duration_seconds = $Duration
        }
        Write-Host "$SmokeTest passed in ${Duration}s" -ForegroundColor Green
        Write-Host "::endgroup::"
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
        if ($_.InvocationInfo.PositionMessage) {
            Write-Host $_.InvocationInfo.PositionMessage -ForegroundColor DarkYellow
        }
        if ($_.ScriptStackTrace) {
            Write-Host $_.ScriptStackTrace -ForegroundColor DarkYellow
        }

        Write-CiError "Smoke test failed" "$SmokeTest failed: $($_.Exception.Message)" "scripts/$SmokeTest"
        Save-FailureDiagnostics -FailedScript $SmokeTest -ErrorRecord $_ -Results $Results
        throw
    }
}

$TotalDuration = [Math]::Round(((Get-Date) - $StartedAt).TotalSeconds, 2)
$Summary = [ordered]@{
    base_url = $BaseUrl
    status = "passed"
    scripts_run = $SmokeTests.Count
    total_duration_seconds = $TotalDuration
    completed_at = (Get-Date).ToUniversalTime().ToString("o")
    results = $Results
}

$Summary | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $SummaryPath

if ($env:GITHUB_STEP_SUMMARY) {
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "## Re-born smoke suite passed"
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "Scripts run: **$($SmokeTests.Count)**"
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "Total duration: **${TotalDuration}s**"
}

Write-CiNotice "Smoke suite passed" "Full Re-born CI smoke suite passed in ${TotalDuration}s."
Write-Host ""
Write-Host "Full Re-born CI smoke suite passed in ${TotalDuration}s." -ForegroundColor Green
Write-Host "Summary written to $SummaryPath" -ForegroundColor Green
