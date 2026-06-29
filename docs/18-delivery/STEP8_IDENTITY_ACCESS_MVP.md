# Step 8 — Identity & Access MVP

## Purpose

Step 8 introduces the first real identity layer for Re-born without changing the architectural rules of the project:

- PHP 8.3+
- no frontend framework
- no backend framework
- modular monolith
- DDD boundaries
- SQLite development database
- MariaDB/MySQL production direction

The goal is not to create a complete enterprise IAM system yet. The goal is to make the MVP safe enough to distinguish real actors in the Repair Intelligence ecosystem.

## Added capabilities

- User registration
- Login with email/password
- Hashed passwords via PHP `password_hash`
- Bearer access tokens
- Token hashes stored in SQLite
- Session revocation / logout
- Current user endpoint
- Role-based authorization
- Admin-only route examples
- Seeded demo users
- Smoke test and PHP identity feature test

## Roles

The initial role model is:

| Role | Meaning |
|---|---|
| `repair_user` | Person trying to repair an object |
| `maker` | CAD creator eligible for royalties |
| `provider` | Local production / repair provider |
| `enterprise` | Company / fleet / circularity client |
| `admin` | Internal Re-born operator |

Public registration is currently limited to:

- `repair_user`
- `maker`
- `provider`

Enterprise and admin users must be provisioned internally.

## New endpoints

```http
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
GET  /api/v1/admin/users
```

`/api/v1/admin/users` and `/api/v1/domain-events` require an admin bearer token.

## Demo accounts

All seeded demo users use password:

```text
password
```

| Role | Email |
|---|---|
| repair_user | repair.user@reborn.local |
| maker | maker@reborn.local |
| provider | provider@reborn.local |
| enterprise | enterprise@reborn.local |
| admin | admin@reborn.local |

## Security notes

This is still an MVP identity layer. Before production:

- replace demo credentials
- add rate limiting
- add email verification
- add password reset flow
- add MFA for admin/provider accounts
- add CSRF protection if cookie sessions are introduced
- add secure CORS policy
- add audit log integration for sensitive actions
- add token rotation for long-running sessions
