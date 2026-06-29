param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }
function CountOf($items) { return (($items | Measure-Object).Count) }

Write-Host "Checking Re-born Step 35 Customer Acceptance, Warranty & Post-Repair Support Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "customer_acceptance_governance") { Fail "Health capabilities missing customer_acceptance_governance." }
if ($health.capabilities -notcontains "warranty_governance") { Fail "Health capabilities missing warranty_governance." }
if ($health.capabilities -notcontains "post_repair_support") { Fail "Health capabilities missing post_repair_support." }
if ($health.capabilities -notcontains "customer_feedback_loop") { Fail "Health capabilities missing customer_feedback_loop." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.customer_care_governance) { Fail "Customer care governance readiness check missing." }
Ok "Readiness includes Step 35 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/customer-care-governance" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.customer_care_governance.summary) { Fail "Customer care governance dashboard failed." }
Ok "Customer care governance dashboard: ok"

$policies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/customer-acceptance-policies?status=active" -Headers $headers
if (-not $policies.success -or (CountOf $policies.customer_acceptance_policies) -lt 1) { Fail "Customer acceptance policies listing failed." }
Ok "Customer acceptance policies: ok"

$warrantyPolicies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/warranty-policies?status=active" -Headers $headers
if (-not $warrantyPolicies.success -or (CountOf $warrantyPolicies.warranty_policies) -lt 1) { Fail "Warranty policies listing failed." }
Ok "Warranty policies: ok"

$proofs = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/proof-of-repair-records?status=all&limit=10" -Headers $headers
$proofId = $null
if ($proofs.success -and (CountOf $proofs.proof_of_repair_records) -gt 0) {
    $proofId = $proofs.proof_of_repair_records[0].id
}

$acceptanceBody = AsJson @{ proof_of_repair_id = $proofId; customer_email = "pilot.customer@reborn.local"; evidence = @{ smoke_test = "step35"; proof_available = [bool]$proofId } }
$acceptance = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/customer-acceptance-records" -Headers $headers -ContentType "application/json" -Body $acceptanceBody
if (-not $acceptance.success -or -not $acceptance.customer_acceptance_record.id) { Fail "Customer acceptance creation failed." }
$acceptanceId = $acceptance.customer_acceptance_record.id
Ok "Customer acceptance creation: ok"

$decision = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/customer-acceptance-records/$acceptanceId/decision" -Headers $headers -ContentType "application/json" -Body (AsJson @{ decision = "rejected_with_issue"; satisfaction_score = 2; issue_summary = "Smoke test customer reports fit issue after repair."; evidence = @{ source = "smoke-test" } })
if (-not $decision.success -or -not $decision.customer_acceptance_result.acceptance_record) { Fail "Customer acceptance decision failed." }
if (-not $decision.customer_acceptance_result.support_ticket) { Fail "Customer issue did not create support ticket." }
if (-not $decision.customer_acceptance_result.warranty_case) { Fail "Customer issue did not create warranty case." }
Ok "Customer acceptance issue workflow: ok"

$acceptances = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/customer-acceptance-records?status=all&limit=30" -Headers $headers
if (-not $acceptances.success -or (CountOf $acceptances.customer_acceptance_records) -lt 1) { Fail "Customer acceptance listing failed." }
Ok "Customer acceptance listing: ok"

$warrantyCases = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/warranty-cases?status=all&limit=30" -Headers $headers
if (-not $warrantyCases.success -or (CountOf $warrantyCases.warranty_cases) -lt 1) { Fail "Warranty cases listing failed." }
$warrantyCaseId = $warrantyCases.warranty_cases[0].id
Ok "Warranty cases listing: ok"

$warrantyUpdate = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/warranty-cases/$warrantyCaseId/status" -Headers $headers -ContentType "application/json" -Body (AsJson @{ status = "resolved"; resolution_summary = "Resolved by Step 35 smoke test governance placeholder." })
if (-not $warrantyUpdate.success -or $warrantyUpdate.warranty_case.status -ne "resolved") { Fail "Warranty case update failed." }
Ok "Warranty case status update: ok"

$tickets = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/post-repair-support-tickets?status=all&limit=30" -Headers $headers
if (-not $tickets.success -or (CountOf $tickets.post_repair_support_tickets) -lt 1) { Fail "Post-repair support tickets listing failed." }
$ticketId = $tickets.post_repair_support_tickets[0].id
Ok "Support ticket listing: ok"

$ticketUpdate = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/post-repair-support-tickets/$ticketId/status" -Headers $headers -ContentType "application/json" -Body (AsJson @{ status = "resolved"; response_summary = "Resolved by Step 35 smoke test." })
if (-not $ticketUpdate.success -or $ticketUpdate.post_repair_support_ticket.status -ne "resolved") { Fail "Support ticket status update failed." }
Ok "Support ticket status update: ok"

$feedback = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/customer-feedback-records" -Headers $headers -ContentType "application/json" -Body (AsJson @{ acceptance_record_id = $acceptanceId; customer_email = "pilot.customer@reborn.local"; rating = 5; nps_score = 9; feedback_text = "Smoke test feedback: repair journey is understandable." })
if (-not $feedback.success -or -not $feedback.customer_feedback_record.id) { Fail "Customer feedback recording failed." }
Ok "Customer feedback recording: ok"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/post-repair-review-items?status=all&limit=30" -Headers $headers
if (-not $reviews.success) { Fail "Post-repair review listing failed." }
Ok "Post-repair reviews listing: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/post-repair-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (CountOf $audit.post_repair_audit_log) -lt 1) { Fail "Post-repair audit log failed." }
Ok "Post-repair audit log: ok"

Write-Host "Step 35 customer acceptance, warranty and post-repair support governance smoke test passed." -ForegroundColor Green
