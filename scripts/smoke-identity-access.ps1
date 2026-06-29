$ErrorActionPreference = "Stop"

$BaseUrl = if ($env:REBORN_BASE_URL) { $env:REBORN_BASE_URL } else { "http://127.0.0.1:8080" }

Write-Host "Checking Re-born Identity & Access API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
Write-Host "Health: $($health.status)" -ForegroundColor Green

$loginBody = @{
  email = "admin@reborn.local"
  password = "password"
} | ConvertTo-Json -Compress

$login = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/auth/login" `
  -ContentType "application/json" `
  -Body $loginBody

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
