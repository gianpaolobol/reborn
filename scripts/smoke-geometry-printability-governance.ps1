param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) { Write-Host $message -ForegroundColor Green }
function Fail($message) { throw $message }
function AsJson($data) { return ($data | ConvertTo-Json -Depth 30) }

Write-Host "Checking Re-born Step 32 CAD/Geometry Validation & Printability Governance API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "cad_geometry_validation") { Fail "Health capabilities missing cad_geometry_validation." }
if ($health.capabilities -notcontains "printability_governance") { Fail "Health capabilities missing printability_governance." }
if ($health.capabilities -notcontains "geometry_human_review") { Fail "Health capabilities missing geometry_human_review." }
Ok "Health capabilities: ok"

$ready = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/ready"
if (-not $ready.success -or -not $ready.readiness) { Fail "Readiness endpoint failed." }
if ($ready.readiness.status -notin @("ready", "degraded")) { Fail "Readiness status is not acceptable: $($ready.readiness.status)" }
if (-not $ready.readiness.checks.geometry_printability) { Fail "Geometry printability readiness check missing." }
Ok "Readiness includes Step 32 checks: $($ready.readiness.status)"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$dashboard = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/geometry-printability" -Headers $headers
if (-not $dashboard.success -or -not $dashboard.geometry_printability.summary) { Fail "Geometry printability dashboard failed." }
Ok "Geometry printability dashboard: ok"

$profiles = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/geometry-validation-profiles?status=active" -Headers $headers
if (-not $profiles.success -or (($profiles.geometry_validation_profiles | Measure-Object).Count -lt 1)) { Fail "Geometry validation profiles listing failed." }
Ok "Geometry validation profiles: ok"

$rules = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/printability-rules?status=active" -Headers $headers
if (-not $rules.success -or (($rules.printability_rules | Measure-Object).Count -lt 1)) { Fail "Printability rules listing failed." }
Ok "Printability rules: ok"

$assetBody = AsJson @{
    file_name = "step32-smoke-repair-bracket.stl"
    file_format = "stl"
    source_type = "ai_artifact_stub"
    source_ref = "step32-smoke-ai-artifact"
    bounding_box_mm = @{ x = 88; y = 36; z = 14 }
    estimated_volume_cm3 = 9.6
    estimated_surface_cm2 = 118.4
    metadata = @{ smoke_test = "step32"; real_cad_kernel = $false }
}
$asset = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/geometry-assets" -Headers $headers -ContentType "application/json" -Body $assetBody
if (-not $asset.success -or -not $asset.geometry_asset.id) { Fail "Geometry asset creation failed." }
Ok "Geometry asset creation: ok"

$evalBody = AsJson @{ profile_key = "fdm_pla_petg_standard"; thin_wall_risk = "medium" }
$evaluation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/geometry-assets/$($asset.geometry_asset.id)/evaluate" -Headers $headers -ContentType "application/json" -Body $evalBody
if (-not $evaluation.success -or -not $evaluation.geometry_evaluation.validation_run.id) { Fail "Geometry asset evaluation failed." }
Ok "Geometry asset evaluation: ok"

$runs = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/geometry-validation-runs?limit=30" -Headers $headers
if (-not $runs.success -or (($runs.geometry_validation_runs | Measure-Object).Count -lt 1)) { Fail "Geometry validation runs listing failed." }
Ok "Geometry validation runs listing: ok"

$findings = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/printability-findings?status=open&limit=30" -Headers $headers
if (-not $findings.success) { Fail "Printability findings listing failed." }
Ok "Printability findings listing: ok"

$reviews = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/geometry-review-items?status=active&limit=30" -Headers $headers
if (-not $reviews.success) { Fail "Geometry review items listing failed." }
if (($reviews.geometry_review_items | Measure-Object).Count -lt 1) { Fail "No active geometry review item was generated." }
Ok "Geometry review queue: ok"

$reviewId = $reviews.geometry_review_items[0].id
$reviewBody = AsJson @{ decision = "approved_with_notes"; notes = "Step 32 smoke test human review completed. Real slicer simulation remains deferred." }
$review = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/geometry-review-items/$reviewId/review" -Headers $headers -ContentType "application/json" -Body $reviewBody
if (-not $review.success -or $review.geometry_review_item.status -ne "closed") { Fail "Geometry human review failed." }
Ok "Geometry human review: ok"

$audit = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/geometry-governance-audit-log?limit=30" -Headers $headers
if (-not $audit.success -or (($audit.geometry_governance_audit_log | Measure-Object).Count -lt 1)) { Fail "Geometry governance audit log failed." }
Ok "Geometry governance audit log: ok"

Write-Host "Step 32 CAD/geometry validation and printability governance smoke test passed." -ForegroundColor Green
