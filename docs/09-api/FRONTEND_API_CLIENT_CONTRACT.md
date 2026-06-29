# Frontend API Client Contract

## Purpose

This document defines how the MVP prototype consumes backend APIs.

The contract is intentionally small and designed to support the first repair journey only.

## Health

```http
GET /api/health
```

Expected use:

- detect whether the backend is live;
- switch between live and mock mode.

## Repair cases

```http
GET /api/v1/repair-cases
```

Used to bootstrap the latest available repair case.

```http
POST /api/v1/repair-cases
```

Required payload:

```json
{
  "title": "Bosch Series 4 — Dishwasher basket wheel",
  "description": "The lower basket wheel is broken.",
  "category": "home_appliance"
}
```

Expected response:

```json
{
  "repair_case": {
    "id": "...",
    "title": "...",
    "description": "...",
    "category": "...",
    "status": "intake_received"
  }
}
```

## Diagnosis

```http
POST /api/v1/repair-cases/{id}/diagnose
```

Expected response:

```json
{
  "repair_case": {},
  "diagnosis": {},
  "repair_paths": [],
  "providers": []
}
```

## Repair paths

```http
GET /api/v1/repair-paths?case_id={id}
```

Expected response:

```json
{
  "repair_paths": []
}
```

## Providers

```http
GET /api/v1/providers
```

Expected response:

```json
{
  "providers": []
}
```

## Knowledge nodes

```http
GET /api/v1/knowledge/nodes
```

Expected response:

```json
{
  "nodes": []
}
```

## Error model

The frontend expects failed API calls to be safe to display in a short message.

The prototype currently treats any error as recoverable and falls back to mock mode.
