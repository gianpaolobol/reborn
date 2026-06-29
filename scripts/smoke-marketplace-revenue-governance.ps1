param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) {
    Write-Host $message -ForegroundColor Green
}

function Fail($message) {
    throw $message
}

function AsJson($data) {
    return ($data | ConvertTo-Json -Depth 30)
}

Write-Host "Checking Re-born Step 28 Marketplace Revenue Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "marketplace_revenue_governance") { Fail "Health capabilities missing marketplace revenue governance." }
if ($health.capabilities -notcontains "repair_credits") { Fail "Health capabilities missing repair credits." }
if ($health.capabilities -notcontains "payout_governance") { Fail "Health capabilities missing payout governance." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.marketplace_revenue) { Fail "Marketplace revenue readiness check missing." }
Ok "Readiness includes Step 28 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/marketplace-revenue" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.marketplace_revenue.summary) { Fail "Marketplace revenue dashboard failed." }
Ok "Marketplace revenue dashboard: ok"

$policies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/marketplace-fee-policies?status=all" -Headers $headers
if (-not $policies.success -or (($policies.fee_policies | Measure-Object).Count -lt 1)) { Fail "Fee policies listing failed." }
Ok "Fee policies listing: ok"

$creditAccounts = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/credit-accounts?status=all&limit=20" -Headers $headers
if (-not $creditAccounts.success -or (($creditAccounts.credit_accounts | Measure-Object).Count -lt 1)) { Fail "Credit accounts listing failed." }
$account = $creditAccounts.credit_accounts | Select-Object -First 1
Ok "Credit accounts listing: ok"

$creditBody = AsJson @{
    account_id = $account.id
    transaction_type = "grant"
    amount_credits = 15
    source_type = "step28_smoke"
    source_id = "credit-grant-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())"
    description = "Step 28 smoke test grant."
}
$creditTxn = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/credit-transactions" -Headers $headers -ContentType "application/json" -Body $creditBody
if (-not $creditTxn.success -or -not $creditTxn.credit_transaction.id) { Fail "Credit transaction failed." }
if ($creditTxn.credit_transaction.amount_credits -ne 15) { Fail "Credit transaction amount mismatch." }
Ok "Credit transaction recorded: ok"

$newCreditBody = AsJson @{
    owner_type = "maker"
    owner_ref = "maker-step28-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())"
    display_name = "Step 28 Maker Credit Account"
    opening_balance_credits = 25
    notes = "Created by Step 28 smoke test."
}
$newCredit = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/credit-accounts" -Headers $headers -ContentType "application/json" -Body $newCreditBody
if (-not $newCredit.success -or -not $newCredit.credit_account.id) { Fail "Credit account creation failed." }
Ok "Credit account creation: ok"

$payoutAccounts = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/payout-accounts?status=all&limit=20" -Headers $headers
if (-not $payoutAccounts.success -or (($payoutAccounts.payout_accounts | Measure-Object).Count -lt 1)) { Fail "Payout accounts listing failed." }
Ok "Payout accounts listing: ok"

$newPayoutBody = AsJson @{
    beneficiary_type = "provider"
    beneficiary_ref = "provider-step28-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())"
    display_name = "Step 28 Provider Payout Account"
    status = "pending"
    currency = "EUR"
    hold_days = 7
    notes = "Created by Step 28 smoke test. Mock only."
}
$newPayout = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/payout-accounts" -Headers $headers -ContentType "application/json" -Body $newPayoutBody
if (-not $newPayout.success -or -not $newPayout.payout_account.id) { Fail "Payout account creation failed." }
Ok "Payout account creation: ok"

$runBody = AsJson @{
    currency = "EUR"
    notes = "Step 28 smoke test payout evaluation. No real money moved."
}
$run = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/payout-runs/evaluate" -Headers $headers -ContentType "application/json" -Body $runBody
if (-not $run.success -or -not $run.payout_run_evaluation.payout_run.id) { Fail "Payout run evaluation failed." }
$payoutRun = $run.payout_run_evaluation.payout_run
if ($payoutRun.status -ne "evaluated") { Fail "Unexpected payout run status after evaluation." }
if ($payoutRun.item_count -lt 1) { Fail "Payout run should contain at least one mock item." }
Ok "Payout run evaluated: $($payoutRun.run_code)"

$items = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/payout-items?limit=20" -Headers $headers
if (-not $items.success -or (($items.payout_items | Measure-Object).Count -lt 1)) { Fail "Payout items listing failed." }
Ok "Payout items listing: ok"

$approveBody = AsJson @{ notes = "Approved by Step 28 smoke test. Mock only." }
$approved = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/payout-runs/$($payoutRun.id)/approve" -Headers $headers -ContentType "application/json" -Body $approveBody
if (-not $approved.success -or $approved.payout_run.status -ne "approved") { Fail "Payout run approval failed." }
Ok "Payout run approval: ok"

$paidBody = AsJson @{ notes = "Marked paid by Step 28 smoke test. No real money moved." }
$paid = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/payout-runs/$($payoutRun.id)/paid" -Headers $headers -ContentType "application/json" -Body $paidBody
if (-not $paid.success -or $paid.payout_run.status -ne "paid") { Fail "Payout run paid mark failed." }
Ok "Payout run marked paid: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/revenue-audit-log?limit=20" -Headers $headers
if (-not $audit.success -or (($audit.revenue_audit_log | Measure-Object).Count -lt 1)) { Fail "Revenue audit log failed." }
Ok "Revenue audit log: ok"

Write-Host "Step 28 marketplace revenue governance smoke test passed." -ForegroundColor Green
