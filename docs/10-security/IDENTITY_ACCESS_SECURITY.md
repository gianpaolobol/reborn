# Identity & Access Security Baseline

## Authentication model

Step 8 uses bearer tokens for API authentication. The plain token is returned only once at login/registration. The database stores only a SHA-256 hash of the token.

This means leaked database rows are not immediately usable as API tokens.

## Password model

Passwords are hashed with PHP `password_hash`, which allows PHP to choose the current secure default algorithm. Password verification uses `password_verify`.

## Authorization model

Authorization is role-based and intentionally simple:

- `repair_user`
- `maker`
- `provider`
- `enterprise`
- `admin`

The `AuthContext` application service is the single access point for bearer authentication and role checks.

## Production blockers

Before production launch, the following must be implemented:

1. Rate limiting for login/register.
2. Email verification.
3. Password reset with expiring one-time tokens.
4. Admin MFA.
5. Audit log writes for authentication and role changes.
6. Account lockout or risk-based throttling.
7. Token expiry cleanup.
8. Secret management outside `.env` for production.
9. CORS hardening.
10. Security headers at web server level.
