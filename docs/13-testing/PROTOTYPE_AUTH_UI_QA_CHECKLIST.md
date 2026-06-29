# Prototype Auth UI QA Checklist

## Pre-flight

- [ ] PHP is installed and available in PowerShell.
- [ ] `pdo_sqlite` and `sqlite3` are enabled.
- [ ] `php scripts/setup-dev.php` runs without error.
- [ ] PHP server is running on `127.0.0.1:8080`.

## Backend smoke

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-identity-access.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-prototype-auth-ui.ps1
```

Expected:

```text
Identity smoke test passed.
Ownership and dashboards smoke test passed.
Prototype auth UI backend dependency smoke test passed.
```

## Browser QA

Open:

```text
http://127.0.0.1:8080/prototype/index.html#/login
```

Check:

- [ ] Login screen appears.
- [ ] Demo account buttons are visible.
- [ ] Admin login works.
- [ ] Banner shows authenticated user.
- [ ] `#/account` shows admin dashboard.
- [ ] `#/maker` shows maker dashboard when admin previews it.
- [ ] Logout clears session and redirects to login.
- [ ] Creating a repair case as guest redirects to login.
- [ ] Creating a repair case as repair user succeeds.

## Known prototype limitation

Role dashboard preview depends on backend permissions. Admin can preview all roles; non-admin users should only access their own allowed role surfaces.
