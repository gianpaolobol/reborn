$ErrorActionPreference = "Stop"

$base = "http://127.0.0.1:8080"

Write-Host "Checking health..."
Invoke-RestMethod -Uri "$base/api/health" -Method GET | ConvertTo-Json -Depth 8

Write-Host "Checking validation error model..."
try {
  Invoke-RestMethod -Uri "$base/api/v1/repair-cases" -Method POST -ContentType "application/json" -Body "{}"
} catch {
  $_.ErrorDetails.Message
}

Write-Host "Creating repair case..."
$body = @{
  title = "Broken espresso machine knob"
  description = "The plastic knob cracked and the machine cannot be turned on reliably."
  category = "home_appliance"
} | ConvertTo-Json

$created = Invoke-RestMethod -Uri "$base/api/v1/repair-cases" -Method POST -ContentType "application/json" -Body $body
$caseId = $created.repair_case.id
$created | ConvertTo-Json -Depth 8

Write-Host "Diagnosing repair case..."
Invoke-RestMethod -Uri "$base/api/v1/repair-cases/$caseId/diagnose" -Method POST | ConvertTo-Json -Depth 8

Write-Host "Reading domain events..."
Invoke-RestMethod -Uri "$base/api/v1/domain-events?limit=10" -Method GET | ConvertTo-Json -Depth 8
