param(
    [string]$DatabasePath = "storage/database/reborn.sqlite",
    [string]$BackupDir = "storage/backups"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $DatabasePath)) {
    throw "Database not found at $DatabasePath. Run php scripts/setup-dev.php first."
}

if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$target = Join-Path $BackupDir "reborn-$timestamp.sqlite"
Copy-Item -Path $DatabasePath -Destination $target
Write-Host "SQLite backup created: $target" -ForegroundColor Green
