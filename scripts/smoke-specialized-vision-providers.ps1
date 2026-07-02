param(
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot ".."))
)

$ErrorActionPreference = "Stop"
function Fail($message) { throw $message }
function Ok($message) { Write-Host $message -ForegroundColor Green }

$files = @(
    "config/ai.php",
    "bootstrap/app.php",
    "src/AI/Application/MultiProviderPhotoRecognitionGateway.php",
    "src/AI/Application/RecognitionQualityGate.php",
    "src/AI/Application/SpecializedVisionResultFactory.php",
    "src/AI/Application/AwsRekognitionCustomLabelsGateway.php",
    "src/AI/Application/ClarifaiCustomVisionGateway.php",
    "src/AI/Application/RoboflowHostedVisionGateway.php"
)

foreach ($file in $files) {
    $path = Join-Path $Root $file
    if (-not (Test-Path $path)) { Fail "Missing file: $file" }
}

$providerFileChecks = @{
    "src/AI/Application/AwsRekognitionCustomLabelsGateway.php" = @("DetectCustomLabels", "AWS4-HMAC-SHA256", "AWS_REKOGNITION_PROJECT_VERSION_ARN")
    "src/AI/Application/ClarifaiCustomVisionGateway.php" = @("CLARIFAI_MODEL_ID", "/outputs", "Authorization: Key")
    "src/AI/Application/RoboflowHostedVisionGateway.php" = @("ROBOFLOW_MODEL_ID", "detect.roboflow.com", "payload_mode")
    "src/AI/Application/MultiProviderPhotoRecognitionGateway.php" = @("awsrekognition", "clarifai", "roboflow", "quality_gate")
    "src/AI/Application/RecognitionQualityGate.php" = @("minimum_final_score", "verify_when_code_visible", "preliminary_needs_verification")
    "bootstrap/app.php" = @("AwsRekognitionCustomLabelsGateway", "ClarifaiCustomVisionGateway", "RoboflowHostedVisionGateway")
    "config/ai.php" = @("AWS_REKOGNITION_ENABLED", "CLARIFAI_ENABLED", "ROBOFLOW_ENABLED", "AI_RECOGNITION_QUALITY_GATE_ENABLED")
}

foreach ($entry in $providerFileChecks.GetEnumerator()) {
    $path = Join-Path $Root $entry.Key
    $content = Get-Content $path -Raw
    foreach ($marker in $entry.Value) {
        if ($content -notmatch [Regex]::Escape($marker)) { Fail "Missing marker '$marker' in $($entry.Key)" }
    }
}

foreach ($phpFile in $files | Where-Object { $_ -like "*.php" }) {
    $path = Join-Path $Root $phpFile
    $result = & php -l $path 2>&1
    if ($LASTEXITCODE -ne 0) { Fail "php -l failed for $phpFile`n$result" }
}

$publicPath = Join-Path $Root "public/prototype/assets/js/app.js"
if (Test-Path $publicPath) {
    $publicContent = Get-Content $publicPath -Raw
    foreach ($secretMarker in @("AWS_REKOGNITION_SECRET_ACCESS_KEY", "CLARIFAI_API_KEY", "ROBOFLOW_API_KEY")) {
        if ($publicContent -match [Regex]::Escape($secretMarker)) { Fail "Secret marker leaked into frontend JS: $secretMarker" }
    }
}

Ok "Specialized vision provider smoke checks passed."
