# Step 10 — Prototype Auth UI & Role Dashboards

## Goal

Connect the static Re-born prototype to the Identity & Access MVP and Repair Case Ownership dashboards introduced in Steps 8 and 9.

The prototype must stop behaving like a generic static showcase and start behaving like an authenticated product surface:

- guest users can view the concept and login screen;
- authenticated users receive a role-aware dashboard;
- demo roles can be switched quickly;
- Bearer tokens are stored in `localStorage` for prototype usage;
- logout revokes the backend session;
- repair case creation now requires a live authenticated session.

## Added UI

- `#/login` demo login screen.
- Login/logout controls in the API banner.
- Auth chip showing guest/signed-in state.
- Role dashboard rendering for:
  - repair user;
  - maker;
  - provider;
  - enterprise;
  - admin.
- Dashboard route reuse:
  - `#/account` loads the authenticated user's dashboard;
  - `#/maker` loads maker dashboard;
  - `#/provider` loads provider dashboard;
  - `#/enterprise` loads enterprise dashboard;
  - `#/admin-ops` loads admin dashboard.

## Demo accounts

All accounts use password `password` after `php scripts/setup-dev.php`:

- `repair.user@reborn.local`
- `maker@reborn.local`
- `provider@reborn.local`
- `enterprise@reborn.local`
- `admin@reborn.local`

## Important behaviour

If the prototype is opened directly from the filesystem, it remains in mock mode.

To test Step 10 with real auth, run:

```powershell
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open:

```text
http://127.0.0.1:8080/prototype/index.html#/login
```

## Scope deliberately not included

Step 10 does not add production-grade auth UI, password reset, email verification screens, multi-factor auth, CSRF/cookie auth, profile editing, or billing. Those belong to later steps.
