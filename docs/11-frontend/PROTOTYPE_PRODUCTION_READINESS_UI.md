# Prototype Production Readiness UI

Step 20 adds the prototype route:

```text
#/readiness
```

The route is intentionally operational rather than consumer-facing. It helps admin/ops understand whether the current local or pilot environment is suitable for broader testing.

## Panels

- Readiness status
- Security policy
- Runtime information
- Deploy checklist
- Readiness snapshot action

## Live mode

When served from:

```powershell
php -S 127.0.0.1:8080 -t public public/index.php
```

and the user is authenticated as admin, the panel loads:

- `GET /api/v1/platform/readiness`
- `GET /api/v1/platform/security-policy`
- `GET /api/v1/platform/runtime`
- `GET /api/v1/platform/deploy-checklist`

The snapshot button calls:

- `POST /api/v1/platform/readiness-snapshots`

## Mock mode

When opened as a static file, the panel shows safe placeholder status and asks the user to start the API server.

## Product framing

The page reinforces that Re-born must become governable, observable and safe before it handles real repairs, payments or provider accountability at scale.
