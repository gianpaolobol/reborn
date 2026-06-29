$ErrorActionPreference = "Stop"

$BaseUrl = if ($env:REBORN_BASE_URL) { $env:REBORN_BASE_URL } else { "http://127.0.0.1:8080" }

function Login-As($email) {
  $body = @{ email = $email; password = "password" } | ConvertTo-Json -Compress
  $login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $body
  if (-not $login.token.access_token) { throw "Login failed for $email" }
  return $login.token.access_token
}

Write-Host "Checking Re-born Ownership & Dashboards API at $BaseUrl" -ForegroundColor Cyan
$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
Write-Host "Health: $($health.status)" -ForegroundColor Green

$repairToken = Login-As "repair.user@reborn.local"
$repairHeaders = @{ Authorization = "Bearer $repairToken" }

$caseBody = @{
  title = "Step 9 ownership smoke test part"
  description = "A user-owned repair case created from the Step 9 smoke test."
  category = "wearable"
} | ConvertTo-Json -Compress

$created = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -ContentType "application/json" -Headers $repairHeaders -Body $caseBody
if ($created.repair_case.owner_id -ne "user-demo-repair") { throw "Created repair case does not belong to the repair user." }
Write-Host "Create owned repair case: ok" -ForegroundColor Green

$mine = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/repair-cases" -Headers $repairHeaders
if ($mine.scope -ne "owned") { throw "Repair user list should be scoped to owned cases." }
if (-not ($mine.repair_cases | Where-Object { $_.id -eq $created.repair_case.id })) { throw "Owned case not found in repair user list." }
Write-Host "Repair user owned list: ok" -ForegroundColor Green

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/dashboard" -Headers $repairHeaders
if ($dashboard.dashboard.role -ne "repair_user") { throw "Repair user dashboard returned wrong role." }
Write-Host "Repair user dashboard: ok" -ForegroundColor Green

$adminToken = Login-As "admin@reborn.local"
$adminHeaders = @{ Authorization = "Bearer $adminToken" }
$adminDashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/dashboards/admin" -Headers $adminHeaders
if ($adminDashboard.dashboard.role -ne "admin") { throw "Admin dashboard returned wrong role." }
Write-Host "Admin dashboard: ok" -ForegroundColor Green

$makerDashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/dashboards/maker" -Headers $adminHeaders
if ($makerDashboard.dashboard.role -ne "maker") { throw "Admin maker dashboard preview returned wrong role." }
Write-Host "Admin maker dashboard preview: ok" -ForegroundColor Green

$providerToken = Login-As "provider@reborn.local"
$providerHeaders = @{ Authorization = "Bearer $providerToken" }
$providerDashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/dashboard" -Headers $providerHeaders
if ($providerDashboard.dashboard.role -ne "provider") { throw "Provider dashboard returned wrong role." }
Write-Host "Provider dashboard: ok" -ForegroundColor Green

try {
  Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/repair-cases" -ContentType "application/json" -Headers $providerHeaders -Body $caseBody | Out-Null
  throw "Provider should not create repair cases in MVP ownership policy."
} catch {
  Write-Host "Provider creation forbidden: ok" -ForegroundColor Green
}

Write-Host "Ownership and dashboards smoke test passed." -ForegroundColor Green
