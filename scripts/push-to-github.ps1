# Re-born GitHub push helper
# Run from the repository root.

Write-Host "Checking Git status..." -ForegroundColor Cyan
git status

Write-Host "Checking GitHub CLI..." -ForegroundColor Cyan
$gh = Get-Command gh -ErrorAction SilentlyContinue

if (-not $gh) {
    Write-Host "GitHub CLI not found. Install it with:" -ForegroundColor Yellow
    Write-Host "winget install --id GitHub.cli" -ForegroundColor Yellow
    exit 1
}

Write-Host "Starting GitHub authentication..." -ForegroundColor Cyan
gh auth login

Write-Host "Ensuring main branch..." -ForegroundColor Cyan
git branch -M main

Write-Host "Ensuring remote origin..." -ForegroundColor Cyan
$remote = git remote get-url origin 2>$null
if (-not $remote) {
    git remote add origin https://github.com/gianpaolobol/reborn.git
}

Write-Host "Adding files..." -ForegroundColor Cyan
git add .

git status

Write-Host "Commit manually if needed:" -ForegroundColor Green
Write-Host "git commit -m \"docs: expand Re-born product operating system\"" -ForegroundColor Green
Write-Host "Then push:" -ForegroundColor Green
Write-Host "git push -u origin main" -ForegroundColor Green
