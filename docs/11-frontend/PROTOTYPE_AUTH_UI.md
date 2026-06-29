# Prototype Auth UI

## Files

- `public/prototype/index.html`
- `public/prototype/assets/js/api-client.js`
- `public/prototype/assets/js/app.js`
- `public/prototype/assets/js/state.js`
- `public/prototype/assets/css/reborn.css`

## State model

The prototype stores auth state in `REBORN_STATE.auth`:

```js
auth: {
  status: 'guest' | 'authenticated',
  user: null | User,
  tokenStored: boolean,
  lastLoginAt: string | null
}
```

Dashboard payloads are stored in `REBORN_STATE.api`:

```js
api: {
  dashboard: null | object,
  roleDashboards: Record<string, object>
}
```

## Token handling

The prototype uses `localStorage` key:

```text
reborn_access_token
```

This is acceptable for the prototype. Production auth decisions are intentionally deferred.

## API calls

The UI calls:

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `GET /api/v1/dashboard`
- `GET /api/v1/dashboards/{role}`
- `POST /api/v1/repair-cases`

## UX principle

The user never logs in “to browse a marketplace”. The user logs in because Re-born needs to remember their repair history, object state, provider trust and learning contribution.
