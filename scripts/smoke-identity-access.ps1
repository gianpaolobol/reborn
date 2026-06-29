$ErrorActionPreference = "Stop"

$BaseUrl = if ($env:REBORN_BASE_URL) { $env:REBORN_BASE_URL } else { "http://127.0.0.1:8080" }

Write-Host "Checking Re-born Identity & Access API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
Write-Host "Health: $($health.status)" -ForegroundColor Green

$loginBody = @{
  email = "admin@reborn.local"
  password = "password"
} | ConvertTo-Json

$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
$token = $login.token.access_token
if (-not $token) { throw "Login did not return an access token." }
Write-Host "Login admin: ok" -ForegroundColor Green

$headers = @{ Authorization = "Bearer $token" }
$me = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/auth/me" -Headers $headers
Write-Host "Me: $($me.user.email) / $($me.user.role)" -ForegroundColor Green

$users = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/admin/users" -Headers $headers
Write-Host "Admin users endpoint: $($users.users.Count) users" -ForegroundColor Green

$events = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/domain-events?limit=5" -Headers $headers
Write-Host "Admin domain events endpoint: $($events.domain_events.Count) events" -ForegroundColor Green

$logout = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/logout" -Headers $headers
Write-Host "Logout: $($logout.logged_out)" -ForegroundColor Green

try {
  Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/auth/me" -Headers $headers | Out-Null
  throw "Expired/revoked token still worked."
} catch {
  Write-Host "Revoked token rejected: ok" -ForegroundColor Green
}

Write-Host "Identity smoke test passed." -ForegroundColor Green
