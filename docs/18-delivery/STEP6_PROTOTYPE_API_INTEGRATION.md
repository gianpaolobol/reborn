# Step 6 — Prototype API Integration

## Purpose

Step 6 connects the static MVP prototype to the PHP 8.3 backend skeleton created in Step 5.

The prototype now behaves in two modes:

1. **Live API mode** when opened through the PHP development server.
2. **Mock mode** when opened directly as a local HTML file or when the API is unavailable.

This keeps the UX prototype usable for product discussions while allowing the team to test the first real backend flow.

## Main entry point

```text
public/prototype/index.html
```

## New frontend integration files

```text
public/prototype/assets/js/api-client.js
public/prototype/assets/js/state.js
public/prototype/assets/js/app.js
public/prototype/assets/css/reborn.css
```

## Live API flow

The prototype now calls:

```text
GET  /api/health
GET  /api/v1/repair-cases
POST /api/v1/repair-cases
POST /api/v1/repair-cases/{id}/diagnose
GET  /api/v1/repair-paths?case_id={id}
GET  /api/v1/providers
GET  /api/v1/knowledge/nodes
```

## User-facing behavior

The prototype displays an API status banner at the top of every screen.

The banner shows:

- Live API
- Mock mode
- API error
- Last sync time
- Refresh API button

## What is now interactive

- The overview can create a demo repair case through the API.
- The intake form can create a real `repair_case` record.
- The capture screen can run backend diagnosis.
- The diagnosis screen reads backend Recognition Engine output.
- Repair paths can come from the backend Decision Engine.
- Providers can come from the backend provider table.
- Admin/Ops surfaces the number of Knowledge Graph nodes available through the API.

## Fallback principle

The prototype must never become unusable simply because the backend is offline.

If the API is unavailable, the prototype continues with local mock data and clearly tells the user that it is in mock mode.

## Development command

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

## Step 6 definition of done

- The prototype loads in the browser.
- The API status banner appears.
- The prototype can detect live vs mock runtime.
- The intake form can call `POST /api/v1/repair-cases`.
- Diagnosis can call `POST /api/v1/repair-cases/{id}/diagnose`.
- Providers and Knowledge Graph nodes are rendered from API data when available.
- No frontend framework has been introduced.
