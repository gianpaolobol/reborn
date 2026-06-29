param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$LogsRoot = Join-Path $Root "storage/logs"
New-Item -ItemType Directory -Force -Path $LogsRoot | Out-Null

$DiagnosticPath = Join-Path $LogsRoot "ci-auth-preflight.json"
$FailurePath = Join-Path $LogsRoot "ci-auth-preflight-failure.json"
$ServerLog = Join-Path $LogsRoot "ci-php-server.log"

function Invoke-CiWebRequest {
    param(
        [string]$Method,
        [string]$Uri,
        [string]$Body = $null,
        [hashtable]$Headers = @{}
    )

    $args = @{
        Method = $Method
        Uri = $Uri
        TimeoutSec = 20
        SkipHttpErrorCheck = $true
    }

    if ($Body -ne $null) {
        $args.Body = $Body
        $args.ContentType = "application/json"
    }

    if ($Headers.Count -gt 0) {
        $args.Headers = $Headers
    }

    return Invoke-WebRequest @args
}

function Try-RunPhpJson {
    param([string[]]$Args)
    try {
        $output = & php @Args 2>&1
        return [pscustomobject]@{
            ok = $LASTEXITCODE -eq 0
            exit_code = $LASTEXITCODE
            output = ($output -join "`n")
        }
    } catch {
        return [pscustomobject]@{
            ok = $false
            exit_code = -1
            output = $_.Exception.Message
        }
    }
}

Write-Host "Running CI API auth preflight against $BaseUrl" -ForegroundColor Cyan

$health = Invoke-CiWebRequest -Method GET -Uri "$BaseUrl/api/health"
$loginBody = @{ email = "admin@reborn.local"; password = "password" } | ConvertTo-Json -Compress
$login = Invoke-CiWebRequest -Method POST -Uri "$BaseUrl/api/v1/auth/login" -Body $loginBody

$diagnostic = [ordered]@{
    checked_at = (Get-Date).ToUniversalTime().ToString("o")
    base_url = $BaseUrl
    health_status = [int]$health.StatusCode
    health_body = $health.Content
    login_status = [int]$login.StatusCode
    login_body = $login.Content
    demo_credentials_verification = Try-RunPhpJson @("scripts/verify-demo-credentials.php")
    server_log_tail = @()
}

if (Test-Path $ServerLog) {
    $diagnostic.server_log_tail = Get-Content $ServerLog -Tail 200
}

$diagnostic | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $DiagnosticPath

if ([int]$health.StatusCode -lt 200 -or [int]$health.StatusCode -ge 300) {
    $diagnostic | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $FailurePath
    throw "API health preflight failed with HTTP $($health.StatusCode). See storage/logs/ci-auth-preflight-failure.json"
}

if ([int]$login.StatusCode -lt 200 -or [int]$login.StatusCode -ge 300) {
    Write-Host "Admin login preflight failed with HTTP $($login.StatusCode)." -ForegroundColor Red
    Write-Host $login.Content -ForegroundColor Red
    $diagnostic | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $FailurePath
    throw "Admin login preflight failed before smoke suite. See storage/logs/ci-auth-preflight-failure.json"
}

$payload = $login.Content | ConvertFrom-Json
$token = $null
if ($payload.token -and $payload.token.access_token) { $token = $payload.token.access_token }
if (-not $token -and $payload.access_token) { $token = $payload.access_token }
if (-not $token -and $payload.data -and $payload.data.token -and $payload.data.token.access_token) { $token = $payload.data.token.access_token }
if (-not $token -and $payload.data -and $payload.data.access_token) { $token = $payload.data.access_token }

if (-not $token) {
    throw "Admin login preflight succeeded but did not return an access token. See storage/logs/ci-auth-preflight.json"
}

$me = Invoke-CiWebRequest -Method GET -Uri "$BaseUrl/api/v1/auth/me" -Headers @{ Authorization = "Bearer $token" }
if ([int]$me.StatusCode -lt 200 -or [int]$me.StatusCode -ge 300) {
    $diagnostic.me_status = [int]$me.StatusCode
    $diagnostic.me_body = $me.Content
    $diagnostic | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $FailurePath
    throw "Auth /me preflight failed with HTTP $($me.StatusCode). See storage/logs/ci-auth-preflight-failure.json"
}

$diagnostic.me_status = [int]$me.StatusCode
$diagnostic.me_body = $me.Content
$diagnostic.success = $true
$diagnostic | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $DiagnosticPath

Write-Host "CI API auth preflight passed." -ForegroundColor Green
