# Re-born Prototype

This is the MVP prototype for Re-born, the Repair Intelligence Platform.

## Runtime modes

### Live API mode

Run the PHP backend:

```powershell
cd C:\REBORN\REBORN
php scripts/doctor.php
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Open:

```text
http://127.0.0.1:8080/prototype/index.html
```

In this mode, the prototype calls the PHP API and SQLite development database.

### Mock mode

Open directly:

```text
public/prototype/index.html
```

In this mode, the prototype uses local mock data from:

```text
public/prototype/assets/js/prototype-data.js
```

## Main integrated flow

```text
Repair intake
→ POST /api/v1/repair-cases
→ Capture placeholder
→ POST /api/v1/repair-cases/{id}/diagnose
→ Repair paths
→ Provider network
→ Checkout preview
```

## Files

```text
index.html
assets/css/reborn.css
assets/js/prototype-data.js
assets/js/state.js
assets/js/api-client.js
assets/js/app.js
```

## Step 10 — Auth UI and role dashboards

Run the backend first:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open:

```text
http://127.0.0.1:8080/prototype/index.html#/login
```

Demo password for every seeded account is:

```text
password
```

Available demo accounts:

- `repair.user@reborn.local`
- `maker@reborn.local`
- `provider@reborn.local`
- `enterprise@reborn.local`
- `admin@reborn.local`

The prototype stores the Step 8 Bearer token in `localStorage` under `reborn_access_token`. Logout calls the backend and clears the token locally.
