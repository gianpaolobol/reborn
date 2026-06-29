$ErrorActionPreference = "Stop"

$BaseUrl = if ($env:REBORN_BASE_URL) { $env:REBORN_BASE_URL } else { "http://127.0.0.1:8080" }

function Ok($message) {
  Write-Host $message -ForegroundColor Green
}

function Info($message) {
  Write-Host $message -ForegroundColor Cyan
}

Info "Checking Re-born Prototype Auth UI at $BaseUrl"

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health" -TimeoutSec 10
Ok "Health: $($health.status)"

$index = Invoke-WebRequest -Method GET -Uri "$BaseUrl/prototype/index.html" -UseBasicParsing -TimeoutSec 10
if ($index.StatusCode -ne 200) {
  throw "Prototype index did not return HTTP 200."
}
Ok "Prototype index: ok"

$assets = @(
  "/prototype/assets/css/reborn.css",
  "/prototype/assets/js/api-client.js",
  "/prototype/assets/js/state.js",
  "/prototype/assets/js/app.js"
)

foreach ($asset in $assets) {
  $res = Invoke-WebRequest -Method GET -Uri "$BaseUrl$asset" -UseBasicParsing -TimeoutSec 10
  if ($res.StatusCode -ne 200) {
    throw "Asset failed: $asset"
  }
  Ok "Asset: $asset ok"
}

function Login($email) {
  $body = @{
    email = $email
    password = "password"
  } | ConvertTo-Json -Compress

  $login = Invoke-RestMethod `
    -Method POST `
    -Uri "$BaseUrl/api/v1/auth/login" `
    -ContentType "application/json" `
    -Body $body `
    -TimeoutSec 10

  if (-not $login.token.access_token) {
    $login | ConvertTo-Json -Depth 10
    throw "Login did not return token for $email"
  }

  Ok "Login ${email}: ok"

  return @{
    Token = $login.token.access_token
    User = $login.user
  }
}

function AuthHeaders($token) {
  return @{ Authorization = "Bearer $token" }
}

$repair = Login "repair.user@reborn.local"
$repairHeaders = AuthHeaders $repair.Token

$me = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/auth/me" `
  -Headers $repairHeaders `
  -TimeoutSec 10

if ($me.user.role -ne "repair_user") {
  throw "Expected repair_user role, got $($me.user.role)"
}
Ok "Repair user /me: ok"

$dashboard = Invoke-RestMethod `
  -Method GET `
  -Uri "$BaseUrl/api/v1/dashboard" `
  -Headers $repairHeaders `
  -TimeoutSec 10

if (-not $dashboard.success) {
  $dashboard | ConvertTo-Json -Depth 10
  throw "Repair user dashboard failed."
}
Ok "Repair user dashboard API: ok"

$caseBody = @{
  title = "Prototype auth smoke case"
  description = "Case created to verify authenticated prototype flow."
  category = "consumer_electronics"
} | ConvertTo-Json -Compress

$case = Invoke-RestMethod `
  -Method POST `
  -Uri "$BaseUrl/api/v1/repair-cases" `
  -ContentType "application/json" `
  -Headers $repairHeaders `
  -Body $caseBody `
  -TimeoutSec 10

if (-not $case.repair_case.id) {
  $case | ConvertTo-Json -Depth 10
  throw "Authenticated repair case creation failed."
}
Ok "Authenticated repair case creation: ok"

$roles = @(
  @{ Email = "maker@reborn.local"; Role = "maker" },
  @{ Email = "provider@reborn.local"; Role = "provider" },
  @{ Email = "enterprise@reborn.local"; Role = "enterprise" },
  @{ Email = "admin@reborn.local"; Role = "admin" }
)

foreach ($role in $roles) {
  $session = Login $role.Email
  $headers = AuthHeaders $session.Token

  $me = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/auth/me" `
    -Headers $headers `
    -TimeoutSec 10

  if ($me.user.role -ne $role.Role) {
    throw "Expected $($role.Role), got $($me.user.role)"
  }

  Ok "$($role.Role) /me: ok"

  $dash = Invoke-RestMethod `
    -Method GET `
    -Uri "$BaseUrl/api/v1/dashboard" `
    -Headers $headers `
    -TimeoutSec 10

  if (-not $dash.success) {
    $dash | ConvertTo-Json -Depth 10
    throw "$($role.Role) dashboard failed."
  }

  Ok "$($role.Role) dashboard API: ok"
}

Ok "Prototype auth UI smoke test passed."
