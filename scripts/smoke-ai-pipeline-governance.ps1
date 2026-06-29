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

Write-Host "Checking Re-born Step 30 AI Pipeline Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "ai_pipeline_governance") { Fail "Health capabilities missing ai_pipeline_governance." }
if ($health.capabilities -notcontains "ai_human_review") { Fail "Health capabilities missing ai_human_review." }
if ($health.capabilities -notcontains "ai_dataset_governance") { Fail "Health capabilities missing ai_dataset_governance." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.ai_pipeline_governance) { Fail "AI pipeline governance readiness check missing." }
Ok "Readiness includes Step 30 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-governance" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.ai_governance.summary) { Fail "AI governance dashboard failed." }
Ok "AI governance dashboard: ok"

$providers = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-model-providers?status=all" -Headers $headers
if (-not $providers.success -or (($providers.ai_model_providers | Measure-Object).Count -lt 1)) { Fail "AI model providers listing failed." }
Ok "AI model providers listing: ok"

$rules = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-safety-rules?status=active" -Headers $headers
if (-not $rules.success -or (($rules.ai_safety_rules | Measure-Object).Count -lt 1)) { Fail "AI safety rules listing failed." }
Ok "AI safety rules listing: ok"

$runBody = AsJson @{
    pipeline_type = "model_generation"
    provider_key = "mock_model_generation_engine"
    source_type = "repair_bounty"
    source_ref = "step30-smoke-bounty"
    input_summary = "Generate a governed repair model concept for a real broken object."
    status = "in_review"
    confidence_score = 66
    risk_level = "high"
    human_review_required = $true
}
$run = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-pipeline-runs" -Headers $headers -ContentType "application/json" -Body $runBody
if (-not $run.success -or -not $run.ai_pipeline_run.id) { Fail "AI pipeline run creation failed." }
Ok "AI pipeline run creation: ok"

$reviewBody = AsJson @{
    decision = "approved"
    quality_score = 88
    safety_score = 84
    dimensional_score = 81
    notes = "Approved by Step 30 smoke test. Pilot only."
    output_summary = "Human reviewer approved the AI output for controlled pilot demonstration."
}
$reviewed = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-pipeline-runs/$($run.ai_pipeline_run.id)/review" -Headers $headers -ContentType "application/json" -Body $reviewBody
if (-not $reviewed.success -or $reviewed.ai_pipeline_run.status -ne "approved") { Fail "AI pipeline run review failed." }
Ok "AI pipeline run review: ok"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-human-reviews?limit=20" -Headers $headers
if (-not $reviews.success -or (($reviews.ai_human_reviews | Measure-Object).Count -lt 1)) { Fail "AI human reviews listing failed." }
Ok "AI human reviews listing: ok"

$datasetBody = AsJson @{
    source_type = "repair_outcome"
    source_ref = "step30-smoke-learning-event"
    object_category = "appliance"
    label = "smoke_test_repair_outcome"
    status = "candidate"
    consent_status = "approved"
    license_status = "pilot_internal"
    quality_score = 76
    metadata = @{ training_use = "dry_run_only"; source = "step30_smoke" }
}
$dataset = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-dataset-items" -Headers $headers -ContentType "application/json" -Body $datasetBody
if (-not $dataset.success -or -not $dataset.ai_dataset_item.id) { Fail "AI dataset item creation failed." }
Ok "AI dataset item creation: ok"

$qualityBody = AsJson @{ pipeline_type = "model_generation"; sample_size = 12 }
$quality = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/ai-quality-evaluations/evaluate" -Headers $headers -ContentType "application/json" -Body $qualityBody
if (-not $quality.success -or -not $quality.ai_quality_evaluation.id) { Fail "AI quality evaluation failed." }
Ok "AI quality evaluation: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/ai-governance-audit-log?limit=20" -Headers $headers
if (-not $audit.success -or (($audit.ai_governance_audit_log | Measure-Object).Count -lt 1)) { Fail "AI governance audit log failed." }
Ok "AI governance audit log: ok"

Write-Host "Step 30 AI pipeline governance smoke test passed." -ForegroundColor Green
