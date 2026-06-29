# Run this after:
# php scripts/setup-dev.php
# php -S 127.0.0.1:8080 -t public public/index.php

Invoke-RestMethod http://127.0.0.1:8080/api/health
Invoke-RestMethod http://127.0.0.1:8080/api/v1/repair-cases

$body = @{
  title = "Broken washing machine handle"
  description = "The handle is cracked and the drawer cannot be opened safely."
  category = "home appliance"
} | ConvertTo-Json

$case = Invoke-RestMethod -Uri http://127.0.0.1:8080/api/v1/repair-cases -Method POST -Body $body -ContentType "application/json"
$case.repair_case.id
Invoke-RestMethod -Uri "http://127.0.0.1:8080/api/v1/repair-cases/$($case.repair_case.id)/diagnose" -Method POST
