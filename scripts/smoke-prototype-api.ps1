$ErrorActionPreference = "Stop"

$BaseUrl = "http://127.0.0.1:8080"

Write-Host "Checking Re-born API health..."
Invoke-RestMethod "$BaseUrl/api/health" | Format-List

Write-Host "Reading providers..."
Invoke-RestMethod "$BaseUrl/api/v1/providers" | ConvertTo-Json -Depth 8

Write-Host "Reading knowledge nodes..."
Invoke-RestMethod "$BaseUrl/api/v1/knowledge/nodes" | ConvertTo-Json -Depth 8

Write-Host "Creating repair case..."
$Body = @{
  title = "Dishwasher wheel smoke test"
  description = "The lower basket wheel is broken and needs a repair path."
  category = "home_appliance"
} | ConvertTo-Json

$Case = Invoke-RestMethod -Method Post -Uri "$BaseUrl/api/v1/repair-cases" -ContentType "application/json" -Body $Body
$Case | ConvertTo-Json -Depth 8

$CaseId = $Case.repair_case.id
Write-Host "Diagnosing repair case $CaseId..."
Invoke-RestMethod -Method Post -Uri "$BaseUrl/api/v1/repair-cases/$CaseId/diagnose" | ConvertTo-Json -Depth 8

Write-Host "Prototype API smoke test completed."
