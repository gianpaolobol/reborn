$ErrorActionPreference = "Stop"
$IdentitySmokeVersion = "STEP38_IDENTITY_SMOKE_GUARD_V3"
Write-Host "Identity smoke script version: $IdentitySmokeVersion" -ForegroundColor Magenta

$BaseUrl = if ($env:REBORN_BASE_URL) { $env:REBORN_BASE_URL } else { "http://127.0.0.1:8080" }
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$LogsRoot = Join-Path $Root "storage/logs"
New-Item -ItemType Directory -Force -Path $LogsRoot | Out-Null

function Invoke-DemoCredentialRepair {
  if (-not $env:CI) { return }

  $ResetScript = Join-Path $Root "scripts/reset-demo-credentials.php"
  $VerifyScript = Join-Path $Root "scripts/verify-demo-credentials.php"

  if (Test-Path $ResetScript) {
    Write-Host "CI demo credential repair: reset demo credentials" -ForegroundColor DarkCyan
    $resetOutput = & php $ResetScript 2>&1
    $resetExitCode = $LASTEXITCODE
    if ($resetOutput) { $resetOutput | ForEach-Object { Write-Host $_ } }
    if ($resetExitCode -ne 0) { throw "reset-demo-credentials.php failed during identity smoke repair." }
  }

  if (Test-Path $VerifyScript) {
    Write-Host "CI demo credential repair: verify demo credentials" -ForegroundColor DarkCyan
    $verifyOutput = & php $VerifyScript 2>&1
    $verifyExitCode = $LASTEXITCODE
    if ($verifyOutput) { $verifyOutput | ForEach-Object { Write-Host $_ } }
    if ($verifyExitCode -ne 0) { throw "verify-demo-credentials.php failed during identity smoke repair." }
  }
}

function Save-IdentityLoginFailureDiagnostics {
  param([object]$ErrorRecord)

  $diagnostic = [ordered]@{
    checked_at = (Get-Date).ToUniversalTime().ToString("o")
    base_url = $BaseUrl
    failed_stage = "smoke-identity-access-login"
    error = $ErrorRecord.Exception.Message
    position = $ErrorRecord.InvocationInfo.PositionMessage
    ready = $null
    health = $null
    demo_credentials_verification = $null
    server_log_tail = @()
  }

  try { $diagnostic.health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 15 } catch { $diagnostic.health = $_.Exception.Message }
  try { $diagnostic.ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready" -TimeoutSec 15 } catch { $diagnostic.ready = $_.Exception.Message }

  $VerifyScript = Join-Path $Root "scripts/verify-demo-credentials.php"
  if (Test-Path $VerifyScript) {
    try { $diagnostic.demo_credentials_verification = (& php $VerifyScript 2>&1) -join "`n" } catch { $diagnostic.demo_credentials_verification = $_.Exception.Message }
  }

  $ServerLog = Join-Path $LogsRoot "ci-php-server.log"
  if (Test-Path $ServerLog) {
    $diagnostic.server_log_tail = Get-Content $ServerLog -Tail 200
  }

  $FailurePath = Join-Path $LogsRoot "ci-identity-login-failure.json"
  $diagnostic | ConvertTo-Json -Depth 12 | Out-File -Encoding UTF8 $FailurePath
}

function Invoke-AdminLogin {
  $loginBody = @{
    email = "admin@reborn.local"
    password = "password"
  } | ConvertTo-Json -Compress

  return Invoke-RestMethod `
    -Method POST `
    -Uri "$BaseUrl/api/v1/auth/login" `
    -ContentType "application/json" `
    -Body $loginBody
}

Write-Host "Checking Re-born Identity & Access API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
Write-Host "Health: $($health.status)" -ForegroundColor Green

try {
  $login = Invoke-AdminLogin
} catch {
  if ($env:CI) {
    Write-Host "Initial CI admin login failed; resetting demo credentials and retrying once." -ForegroundColor Yellow
    Invoke-DemoCredentialRepair
    try {
      $login = Invoke-AdminLogin
    } catch {
      Save-IdentityLoginFailureDiagnostics $_
      throw
    }
  } else {
    Save-IdentityLoginFailureDiagnostics $_
    throw
  }
}

Write-Host "Login raw response:" -ForegroundColor Yellow
$login | ConvertTo-Json -Depth 10

$token = $null

if ($login.token -and $login.token.access_token) {
  $token = $login.token.access_token
}

if (-not $token -and $login.access_token) {
  $token = $login.access_token
}

if (-not $token -and $login.data -and $login.data.token -and $login.data.token.access_token) {
  $token = $login.data.token.access_token
}

if (-not $token -and $login.data -and $login.data.access_token) {
  $token = $login.data.access_token
}

if (-not $token) {
  throw "Login did not return an access token. See raw response above."
}

Write-Host "Login admin: ok" -ForegroundColor Green

$headers = @{ Authorization = "Bearer $token" }

$me = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/auth/me" `
  -Headers $headers

Write-Host "Me raw response:" -ForegroundColor Yellow
$me | ConvertTo-Json -Depth 10

$userEmail = if ($me.user.email) { $me.user.email } elseif ($me.data.user.email) { $me.data.user.email } else { "unknown" }
$userRole = if ($me.user.role) { $me.user.role } elseif ($me.data.user.role) { $me.data.user.role } else { "unknown" }

Write-Host "Me: $userEmail / $userRole" -ForegroundColor Green

$users = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/admin/users" `
  -Headers $headers

Write-Host "Admin users endpoint: $($users.users.Count) users" -ForegroundColor Green

$events = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/domain-events?limit=5" `
  -Headers $headers

Write-Host "Admin domain events endpoint: $($events.domain_events.Count) events" -ForegroundColor Green

$logout = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/auth/logout" `
  -Headers $headers

Write-Host "Logout: ok" -ForegroundColor Green

try {
  Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/auth/me" `
    -Headers $headers | Out-Null

  throw "Expired/revoked token still worked."
} catch {
  Write-Host "Revoked token rejected: ok" -ForegroundColor Green
}

Write-Host "Identity smoke test passed." -ForegroundColor Green
