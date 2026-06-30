param(
    [string]$BaseUrl = "http://127.0.0.1:8080",
    [switch]$AllowFailedSuite
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$Step45Version = "STEP45_5_1_RELEASE_EVIDENCE_CI_LOCALIZATION_HOTFIX_V1"
Write-Host "Release evidence script version: $Step45Version" -ForegroundColor Magenta

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$ScriptsRoot = Join-Path $Root "scripts"
$LogsRoot = Join-Path $Root "storage/logs"
New-Item -ItemType Directory -Force -Path $LogsRoot | Out-Null

$SmokeSummaryPath = Join-Path $LogsRoot "ci-smoke-results.json"
$MatrixPath = Join-Path $LogsRoot "ci-regression-test-matrix.json"
$EvidencePath = Join-Path $LogsRoot "ci-release-evidence.json"
$QualityGatePath = Join-Path $LogsRoot "ci-quality-gate.json"
$MarkdownSummaryPath = Join-Path $LogsRoot "ci-release-evidence.md"

function Escape-GitHubCommandValue([string]$Value) {
    if ($null -eq $Value) { return "" }
    return $Value.Replace('%', '%25').Replace("`r", '%0D').Replace("`n", '%0A')
}

function Write-CiError([string]$Title, [string]$Message, [string]$File = "scripts/ci-release-evidence.ps1") {
    $safeTitle = Escape-GitHubCommandValue $Title
    $safeMessage = Escape-GitHubCommandValue $Message
    Write-Host "::error file=$File,title=$safeTitle::$safeMessage"
}

function Write-CiNotice([string]$Title, [string]$Message) {
    $safeTitle = Escape-GitHubCommandValue $Title
    $safeMessage = Escape-GitHubCommandValue $Message
    Write-Host "::notice title=$safeTitle::$safeMessage"
}

function Try-JsonGet([string]$Uri) {
    try {
        return Invoke-RestMethod -Method GET -Uri $Uri -TimeoutSec 15
    } catch {
        return [pscustomobject]@{
            success = $false
            uri = $Uri
            error = $_.Exception.Message
        }
    }
}


function Get-JsonProperty([object]$Object, [string]$Name, [object]$Default = $null) {
    if ($null -eq $Object) { return $Default }
    if ($Object -is [System.Collections.IDictionary]) {
        if ($Object.Contains($Name)) { return $Object[$Name] }
        return $Default
    }
    $property = $Object.PSObject.Properties[$Name]
    if ($null -eq $property) { return $Default }
    return $property.Value
}
function Get-MatrixRowValue([object]$Row, [string]$Name, [object]$Default = $null) {
    return Get-JsonProperty $Row $Name $Default
}

function Has-ErrorProperty([object]$Object) {
    if ($null -eq $Object) { return $true }
    $property = $Object.PSObject.Properties['error']
    if ($null -eq $property) { return $false }
    return -not [string]::IsNullOrWhiteSpace([string]$property.Value)
}

$RegressionMatrix = @(
    [ordered]@{ order = 1; step = 8; domain = "Identity"; capability = "Identity & Access MVP"; script = "smoke-identity-access.ps1"; gate = "critical"; asset = "Operational Trust" },
    [ordered]@{ order = 2; step = 9; domain = "Dashboard"; capability = "Ownership & Role Dashboards"; script = "smoke-ownership-dashboards.ps1"; gate = "critical"; asset = "Operational Trust" },
    [ordered]@{ order = 3; step = 10; domain = "Frontend"; capability = "Prototype Auth UI"; script = "smoke-prototype-auth-ui.ps1"; gate = "critical"; asset = "Community" },
    [ordered]@{ order = 4; step = 11; domain = "Repair / AI"; capability = "Upload & Recognition Pipeline"; script = "smoke-repair-upload-recognition.ps1"; gate = "critical"; asset = "AI Learning" },
    [ordered]@{ order = 5; step = 12; domain = "Repair"; capability = "Repair Path Decision"; script = "smoke-repair-path-decision.ps1"; gate = "critical"; asset = "Knowledge Graph" },
    [ordered]@{ order = 6; step = 13; domain = "Provider"; capability = "Provider Match & Quote"; script = "smoke-provider-match-quote.ps1"; gate = "critical"; asset = "Marketplace Liquidity" },
    [ordered]@{ order = 7; step = 14; domain = "Marketplace"; capability = "Order & Payment Intent"; script = "smoke-repair-order-payment-intent.ps1"; gate = "critical"; asset = "Marketplace Liquidity" },
    [ordered]@{ order = 8; step = 15; domain = "Fulfilment"; capability = "Fulfilment Workflow"; script = "smoke-repair-fulfilment-workflow.ps1"; gate = "critical"; asset = "Objects Saved" },
    [ordered]@{ order = 9; step = 16; domain = "Learning"; capability = "Completion & Learning"; script = "smoke-repair-completion-learning.ps1"; gate = "critical"; asset = "AI Learning" },
    [ordered]@{ order = 10; step = 17; domain = "Trust"; capability = "Provider Quality Scoring"; script = "smoke-provider-trust-quality.ps1"; gate = "critical"; asset = "Provider Quality" },
    [ordered]@{ order = 11; step = 18; domain = "Governance"; capability = "Ranking & Marketplace Governance"; script = "smoke-provider-ranking-governance.ps1"; gate = "critical"; asset = "Operational Trust" },
    [ordered]@{ order = 12; step = 19; domain = "Operations"; capability = "Admin Ops & Moderation"; script = "smoke-admin-ops-moderation.ps1"; gate = "critical"; asset = "Operational Trust" },
    [ordered]@{ order = 13; step = 20; domain = "Platform"; capability = "Production Readiness"; script = "smoke-production-readiness.ps1"; gate = "release-blocking"; asset = "Platform Readiness" },
    [ordered]@{ order = 14; step = 21; domain = "Platform"; capability = "Observability & Backup"; script = "smoke-observability-ops.ps1"; gate = "release-blocking"; asset = "Platform Readiness" },
    [ordered]@{ order = 15; step = 22; domain = "Platform"; capability = "Incident Response & Status"; script = "smoke-incident-response-status.ps1"; gate = "release-blocking"; asset = "Operational Trust" },
    [ordered]@{ order = 16; step = 23; domain = "Platform"; capability = "Notifications & Escalation"; script = "smoke-notification-escalation.ps1"; gate = "release-blocking"; asset = "Operational Trust" },
    [ordered]@{ order = 17; step = 24; domain = "Platform"; capability = "SLA & Operational Governance"; script = "smoke-service-governance-sla.ps1"; gate = "release-blocking"; asset = "Operational Trust" },
    [ordered]@{ order = 18; step = 25; domain = "Platform"; capability = "Privacy & Data Governance"; script = "smoke-privacy-data-governance.ps1"; gate = "release-blocking"; asset = "Operational Trust" },
    [ordered]@{ order = 19; step = 26; domain = "Platform"; capability = "Beta Release Management"; script = "smoke-beta-release-management.ps1"; gate = "release-blocking"; asset = "Platform Readiness" },
    [ordered]@{ order = 20; step = 27; domain = "Partner"; capability = "Partner Onboarding Governance"; script = "smoke-partner-onboarding-governance.ps1"; gate = "release-blocking"; asset = "Enterprise Value" },
    [ordered]@{ order = 21; step = 28; domain = "Marketplace"; capability = "Revenue & Payout Governance"; script = "smoke-marketplace-revenue-governance.ps1"; gate = "critical"; asset = "Marketplace Liquidity" },
    [ordered]@{ order = 22; step = 29; domain = "Maker Economy"; capability = "Model Licensing & Bounties"; script = "smoke-maker-economy-governance.ps1"; gate = "critical"; asset = "Community" },
    [ordered]@{ order = 23; step = 30; domain = "AI"; capability = "AI Pipeline Governance"; script = "smoke-ai-pipeline-governance.ps1"; gate = "critical"; asset = "AI Learning" },
    [ordered]@{ order = 24; step = 31; domain = "AI"; capability = "AI Provider Sandbox"; script = "smoke-ai-provider-sandbox-orchestration.ps1"; gate = "critical"; asset = "AI Learning" },
    [ordered]@{ order = 25; step = 32; domain = "Geometry"; capability = "CAD Geometry & Printability"; script = "smoke-geometry-printability-governance.ps1"; gate = "critical"; asset = "Provider Quality" },
    [ordered]@{ order = 26; step = 33; domain = "Provider"; capability = "Provider Routing"; script = "smoke-provider-routing-governance.ps1"; gate = "critical"; asset = "Marketplace Liquidity" },
    [ordered]@{ order = 27; step = 34; domain = "Fulfilment"; capability = "Dispatch & Proof-of-Repair"; script = "smoke-dispatch-proof-governance.ps1"; gate = "critical"; asset = "Objects Saved" },
    [ordered]@{ order = 28; step = 35; domain = "Customer Care"; capability = "Acceptance, Warranty & Support"; script = "smoke-customer-care-warranty-support.ps1"; gate = "critical"; asset = "Operational Trust" },
    [ordered]@{ order = 29; step = 36; domain = "Sustainability"; capability = "Impact & Circularity Metrics"; script = "smoke-sustainability-impact-circularity.ps1"; gate = "critical"; asset = "Sustainability Impact" },
    [ordered]@{ order = 30; step = 37; domain = "Investor"; capability = "KPI & Board Reporting"; script = "smoke-investor-reporting-board-readiness.ps1"; gate = "critical"; asset = "Enterprise Value" },
    [ordered]@{ order = 31; step = 40; domain = "Demo"; capability = "Guided Repair Journey & Investor Walkthrough"; script = "smoke-demo-walkthrough-investor-journey.ps1"; gate = "release-blocking"; asset = "Enterprise Value" },
    [ordered]@{ order = 32; step = 41; domain = "Pilot"; capability = "Demo Data Room, Pilot Launch Pack & Stakeholder Feedback Loop"; script = "smoke-demo-data-room-pilot-feedback-loop.ps1"; gate = "release-blocking"; asset = "Enterprise Value" },
    [ordered]@{ order = 33; step = 42; domain = "Pilot"; capability = "Public Pilot Demo, Partner Intake & Real-World Validation"; script = "smoke-public-pilot-real-world-validation.ps1"; gate = "release-blocking"; asset = "Real-World Validation" },
    [ordered]@{ order = 34; step = 43; domain = "UX"; capability = "Guided User Repair Experience Simplification"; script = "smoke-guided-user-repair-experience.ps1"; gate = "release-blocking"; asset = "User Activation" },
    [ordered]@{ order = 35; step = 44; domain = "UX"; capability = "Repair-First Offer Architecture & Replacement-Part Wizard"; script = "smoke-repair-first-offer-architecture.ps1"; gate = "release-blocking"; asset = "User Activation" },
    [ordered]@{ order = 36; step = 45; domain = "AI / UX"; capability = "AI Photo Recognition & Replacement-Part Brief"; script = "smoke-ai-photo-recognition-replacement-brief.ps1"; gate = "release-blocking"; asset = "AI Learning" }
)

$SmokeSummary = $null
$SmokeSummaryMissing = -not (Test-Path $SmokeSummaryPath)
if (-not $SmokeSummaryMissing) {
    $SmokeSummary = Get-Content -Raw -Path $SmokeSummaryPath | ConvertFrom-Json
}

$ResultByScript = @{}
$SmokeResults = Get-JsonProperty $SmokeSummary "results" @()
if ($SmokeSummary -and $SmokeResults) {
    foreach ($result in @($SmokeResults)) {
        $ResultByScript[(Get-JsonProperty $result "script" "")] = $result
    }
}

$MatrixRows = @()
$MissingScripts = @()
$MissingResults = @()
$FailedRows = @()

foreach ($row in $RegressionMatrix) {
    $scriptPath = Join-Path $ScriptsRoot $row.script
    $scriptExists = Test-Path $scriptPath
    if (-not $scriptExists) { $MissingScripts += $row.script }

    $result = $null
    if ($ResultByScript.ContainsKey($row.script)) { $result = $ResultByScript[$row.script] }
    if ($null -eq $result) { $MissingResults += $row.script }

    $resultStatus = Get-JsonProperty $result "status" $null
    $status = if ($null -ne $resultStatus -and [string]$resultStatus -ne "") { [string]$resultStatus } elseif ($SmokeSummaryMissing) { "not_run" } else { "missing_result" }
    if ($status -ne "passed") { $FailedRows += $row.script }

    $MatrixRows += [ordered]@{
        order = $row.order
        step = $row.step
        domain = $row.domain
        capability = $row.capability
        strategic_asset = $row.asset
        smoke_script = $row.script
        gate = $row.gate
        script_exists = $scriptExists
        status = $status
        duration_seconds = Get-JsonProperty $result "duration_seconds" $null
        error = Get-JsonProperty $result "error" $null
    }
}

$UnknownResults = @()
if ($SmokeSummary -and $SmokeResults) {
    $knownScripts = @($RegressionMatrix | ForEach-Object { $_.script })
    foreach ($result in @($SmokeResults)) {
        $scriptName = Get-JsonProperty $result "script" ""
        if ($knownScripts -notcontains $scriptName) { $UnknownResults += $scriptName }
    }
}

$Readiness = Try-JsonGet "$BaseUrl/api/ready"
$Health = Try-JsonGet "$BaseUrl/api/health"
$StatusPage = Try-JsonGet "$BaseUrl/api/status"

$GateChecks = @(
    [ordered]@{ name = "smoke_summary_present"; status = if ($SmokeSummaryMissing) { "failed" } else { "passed" }; detail = $SmokeSummaryPath },
    [ordered]@{ name = "smoke_suite_passed"; status = if ($SmokeSummary -and (Get-JsonProperty $SmokeSummary "status" "") -eq "passed") { "passed" } else { "failed" }; detail = if ($SmokeSummary) { Get-JsonProperty $SmokeSummary "status" "missing" } else { "missing" } },
    [ordered]@{ name = "all_matrix_scripts_exist"; status = if ($MissingScripts.Count -eq 0) { "passed" } else { "failed" }; detail = $MissingScripts },
    [ordered]@{ name = "all_matrix_results_present"; status = if ($MissingResults.Count -eq 0) { "passed" } else { "failed" }; detail = $MissingResults },
    [ordered]@{ name = "no_unknown_smoke_results"; status = if ($UnknownResults.Count -eq 0) { "passed" } else { "failed" }; detail = $UnknownResults },
    [ordered]@{ name = "all_release_blocking_gates_passed"; status = if ((@($MatrixRows | Where-Object { (Get-MatrixRowValue $_ "gate" "") -eq "release-blocking" -and (Get-MatrixRowValue $_ "status" "") -ne "passed" })).Count -eq 0) { "passed" } else { "failed" }; detail = @($MatrixRows | Where-Object { (Get-MatrixRowValue $_ "gate" "") -eq "release-blocking" -and (Get-MatrixRowValue $_ "status" "") -ne "passed" } | ForEach-Object { Get-MatrixRowValue $_ "smoke_script" "unknown" }) },
    [ordered]@{ name = "api_health_reachable"; status = if (Has-ErrorProperty $Health) { "failed" } else { "passed" }; detail = $Health },
    [ordered]@{ name = "api_readiness_reachable"; status = if (Has-ErrorProperty $Readiness) { "failed" } else { "passed" }; detail = $Readiness },
    [ordered]@{ name = "status_page_reachable"; status = if (Has-ErrorProperty $StatusPage) { "failed" } else { "passed" }; detail = $StatusPage }
)

$GateStatus = if ((@($GateChecks | Where-Object { $_.status -ne "passed" })).Count -eq 0) { "passed" } else { "failed" }

$MatrixPayload = [ordered]@{
    version = $Step45Version
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
    total_rows = $MatrixRows.Count
    release_blocking_rows = (@($MatrixRows | Where-Object { (Get-MatrixRowValue $_ "gate" "") -eq "release-blocking" })).Count
    domains = @($MatrixRows | Group-Object domain | ForEach-Object { [ordered]@{ domain = $_.Name; count = $_.Count } })
    strategic_assets = @($MatrixRows | Group-Object strategic_asset | ForEach-Object { [ordered]@{ asset = $_.Name; count = $_.Count } })
    rows = $MatrixRows
}
$MatrixPayload | ConvertTo-Json -Depth 16 | Out-File -Encoding UTF8 $MatrixPath

$Evidence = [ordered]@{
    version = $Step45Version
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
    repository = $env:GITHUB_REPOSITORY
    workflow = $env:GITHUB_WORKFLOW
    run_id = $env:GITHUB_RUN_ID
    run_attempt = $env:GITHUB_RUN_ATTEMPT
    ref = $env:GITHUB_REF
    sha = $env:GITHUB_SHA
    actor = $env:GITHUB_ACTOR
    event_name = $env:GITHUB_EVENT_NAME
    base_url = $BaseUrl
    smoke_summary = $SmokeSummary
    regression_matrix_file = "storage/logs/ci-regression-test-matrix.json"
    quality_gate_file = "storage/logs/ci-quality-gate.json"
    runtime = [ordered]@{
        php_version = (& php -r 'echo PHP_VERSION;' 2>$null)
        powershell = $PSVersionTable.PSVersion.ToString()
        os = [System.Runtime.InteropServices.RuntimeInformation]::OSDescription
    }
    api = [ordered]@{
        health = $Health
        readiness = $Readiness
        status_page = $StatusPage
    }
}
$Evidence | ConvertTo-Json -Depth 18 | Out-File -Encoding UTF8 $EvidencePath

$QualityGate = [ordered]@{
    version = $Step45Version
    status = $GateStatus
    generated_at = $Evidence.generated_at
    total_checks = $GateChecks.Count
    passed_checks = (@($GateChecks | Where-Object { $_.status -eq "passed" })).Count
    failed_checks = (@($GateChecks | Where-Object { $_.status -ne "passed" })).Count
    checks = $GateChecks
}
$QualityGate | ConvertTo-Json -Depth 18 | Out-File -Encoding UTF8 $QualityGatePath

$markdown = @()
$markdown += "# Re-born CI Release Evidence"
$markdown += ""
$markdown += ("Version: {0}" -f $Step45Version)
$GeneratedAtForMarkdown = [string]$Evidence.generated_at
$markdown += ("Generated at: {0}" -f $GeneratedAtForMarkdown)
$markdown += "Quality gate: **$GateStatus**"
$SmokeStatusForMarkdown = if ($SmokeSummary) { Get-JsonProperty $SmokeSummary "status" "missing" } else { "missing" }
$markdown += "Smoke suite: **$SmokeStatusForMarkdown**"
$markdown += "Matrix rows: **$($MatrixRows.Count)**"
$markdown += ""
$markdown += "## Failed checks"
$failedChecks = @($GateChecks | Where-Object { $_.status -ne "passed" })
if ($failedChecks.Count -eq 0) {
    $markdown += "No failed checks."
} else {
    foreach ($check in $failedChecks) { $markdown += ("- {0}" -f $check.name) }
}
$markdown += ""
$markdown += "## Files"
$markdown += '- `storage/logs/ci-smoke-results.json`'
$markdown += '- `storage/logs/ci-regression-test-matrix.json`'
$markdown += '- `storage/logs/ci-release-evidence.json`'
$markdown += '- `storage/logs/ci-quality-gate.json`'
$markdown -join "`n" | Out-File -Encoding UTF8 $MarkdownSummaryPath

if ($env:GITHUB_STEP_SUMMARY) {
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "## Step 45 release quality gate"
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "Quality gate: **$GateStatus**"
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value "Matrix rows: **$($MatrixRows.Count)**"
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value ""
    Add-Content -Path $env:GITHUB_STEP_SUMMARY -Value 'Evidence files are uploaded as the `reborn-ci-release-evidence` artifact.'
}

if ($GateStatus -eq "passed") {
    Write-CiNotice "Release quality gate passed" "Step 45 release evidence generated and quality gate passed."
    Write-Host "Step 45 release evidence generated. Quality gate passed." -ForegroundColor Green
    exit 0
}

Write-CiError "Release quality gate failed" "Step 45 quality gate failed. See storage/logs/ci-quality-gate.json."
Write-Host "Step 45 release evidence generated. Quality gate failed." -ForegroundColor Red
if ($AllowFailedSuite) {
    exit 0
}
exit 1
