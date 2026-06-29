# Identity API Contract

## Register

```http
POST /api/v1/auth/register
Content-Type: application/json
```

```json
{
  "name": "Demo Maker",
  "email": "maker@example.com",
  "password": "password123",
  "role": "maker"
}
```

Allowed public roles:

- `repair_user`
- `maker`
- `provider`

## Login

```http
POST /api/v1/auth/login
Content-Type: application/json
```

```json
{
  "email": "admin@reborn.local",
  "password": "password"
}
```

Response:

```json
{
  "success": true,
  "user": {
    "id": "user-demo-admin",
    "email": "admin@reborn.local",
    "name": "Demo Admin",
    "role": "admin"
  },
  "token": {
    "type": "Bearer",
    "access_token": "rbn_...",
    "expires_at": "2026-07-06T00:00:00+00:00"
  }
}
```

## Current user

```http
GET /api/v1/auth/me
Authorization: Bearer {token}
```

## Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

## Admin users

```http
GET /api/v1/admin/users
Authorization: Bearer {admin_token}
```

Requires role:

```text
admin
```
