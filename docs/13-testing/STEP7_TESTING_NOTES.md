# Step 7 Testing Notes

## Syntax check

Run from project root:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Feature checks

```powershell
php scripts/run-feature-tests.php
```

The feature script verifies that migrations create the key tables required by the MVP backend.

## API smoke checks

Start the server:

```powershell
php -S 127.0.0.1:8080 -t public public/index.php
```

Then run:

```powershell
.\scripts\smoke-backend-hardening.ps1
```
