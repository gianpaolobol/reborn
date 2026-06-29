# STEP 22 — Incident Response, Alerting & Status Management v1

## Obiettivo

Step 22 trasforma lo strato di osservabilità introdotto nello Step 21 in un sistema operativo per gestire problemi reali durante demo, pilot e beta privata.

Il principio resta quello dell'handoff: non aggiungere feature decorative, ma rendere Re-born più governabile, verificabile e mantenibile.

## Cosa aggiunge

- Alert rules persistenti in SQLite.
- Valutazione alert basata su readiness, metriche HTTP e backup.
- Alert lifecycle: open, acknowledged, resolved.
- Incident lifecycle: investigating, identified, monitoring, resolved.
- Status updates tracciati e collegabili agli incidenti.
- Status page locale/pilot leggibile da `/api/status`.
- Maintenance windows pianificabili e chiudibili.
- Dashboard prototipo admin `#/incidents`.
- Smoke test dedicato `scripts/smoke-incident-response-status.ps1`.

## Nuove tabelle

Migration:

```text
016_incident_response_status_management.sql
```

Tabelle:

```text
platform_alert_rules
platform_alerts
platform_incidents
platform_status_updates
platform_maintenance_windows
```

## Nuovi endpoint

Endpoint pubblico locale/pilot:

```text
GET /api/status
GET /api/v1/platform/status-page
```

Endpoint admin-only:

```text
GET  /api/v1/platform/incident-response
GET  /api/v1/platform/alert-rules
POST /api/v1/platform/alerts/evaluate
GET  /api/v1/platform/alerts
POST /api/v1/platform/alerts/{id}/acknowledge
POST /api/v1/platform/alerts/{id}/resolve
GET  /api/v1/platform/incidents
POST /api/v1/platform/incidents
POST /api/v1/platform/incidents/{id}/status
GET  /api/v1/platform/status-updates
POST /api/v1/platform/status-updates
GET  /api/v1/platform/maintenance-windows
POST /api/v1/platform/maintenance-windows
POST /api/v1/platform/maintenance-windows/{id}/close
```

## Regole alert seed

Lo Step 22 crea regole iniziali per:

- readiness `not_ready`;
- errori API 5xx nella finestra recente;
- latenza media API sopra soglia pilot;
- backup SQLite mancante o più vecchio di 24 ore.

Le regole sono volutamente semplici e locali: servono a rendere il flusso operativo testabile prima di introdurre monitoraggio esterno.

## UI prototipo

Nuova rotta:

```text
http://127.0.0.1:8080/prototype/index.html#/incidents
```

Richiede login admin:

```text
admin@reborn.local
password
```

Azioni disponibili dalla UI:

- Evaluate alerts;
- Create demo incident;
- Post status update;
- Schedule maintenance;
- acknowledge/resolve alert;
- move incident to monitoring;
- resolve incident;
- close maintenance window.

## Smoke test

Con server già aperto in una finestra PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-incident-response-status.ps1
```

Lo smoke test verifica:

1. capabilities Step 22 in `/api/health`;
2. readiness con check `incident_response`;
3. status page pubblica;
4. login admin;
5. alert rules;
6. alert evaluation;
7. creazione e risoluzione incidente;
8. status update;
9. maintenance window;
10. dashboard incident response.

## Uso operativo consigliato

Prima di una demo o beta privata:

```powershell
php scripts/setup-dev.php
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-production-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-observability-ops.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-incident-response-status.ps1
```

Poi aprire:

```text
/prototype/index.html#/observability
/prototype/index.html#/incidents
```

## Limiti intenzionali

Step 22 non introduce ancora:

- invio email/SMS/slack reale;
- monitoraggio esterno uptime;
- escalation legale/commerciale avanzata;
- incident policy enterprise;
- SLA provider reali.

Questi punti sono candidati per step successivi.

## Commit suggerito

```powershell
git status
git add .
git commit -m "platform: add incident response and status management v1"
git push
```
