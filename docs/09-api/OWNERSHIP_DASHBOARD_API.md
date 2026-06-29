# Ownership & Dashboard API

## Auth

All ownership and dashboard endpoints require a bearer token from:

```text
POST /api/v1/auth/login
```

## Current dashboard

```text
GET /api/v1/dashboard
Authorization: Bearer <token>
```

Returns the dashboard matching the authenticated user's role.

## Role dashboards

```text
GET /api/v1/dashboards/repair-user
GET /api/v1/dashboards/maker
GET /api/v1/dashboards/provider
GET /api/v1/dashboards/enterprise
GET /api/v1/dashboards/admin
```

A user may access their own role dashboard. Admins may access all role dashboards as previews.

## Repair case ownership

Creating a repair case now assigns:

```json
{
  "owner_id": "authenticated_user_id"
}
```

Repair users list only their own cases. Admin, maker, provider and enterprise roles use broader role-visible lists for MVP discovery and operational workflows.
