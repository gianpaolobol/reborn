# Prototype API Integration

## Design rule

The prototype remains framework-free:

```text
HTML5
CSS3
Vanilla JavaScript
```

No React, Vue, Angular, Bootstrap or frontend build chain is introduced at this stage.

## Runtime modes

### Live API mode

Live mode is active when the prototype runs from an HTTP origin and can call `/api/health`.

Example:

```text
http://127.0.0.1:8080/prototype/index.html
```

### Mock mode

Mock mode is active when:

- the file is opened directly from disk;
- the PHP server is not running;
- the API is unreachable;
- the request times out.

Mock mode uses `prototype-data.js`.

## API client

File:

```text
public/prototype/assets/js/api-client.js
```

Responsibility:

- check API health;
- centralize JSON requests;
- apply a timeout;
- expose a small frontend API wrapper;
- avoid leaking fetch logic into view functions.

## State

File:

```text
public/prototype/assets/js/state.js
```

The state object now includes:

```text
api.status
api.mode
api.message
api.lastError
api.repairCase
api.repairCases
api.diagnosis
api.repairPaths
api.providers
api.knowledgeNodes
api.lastSyncAt
```

## Rendering strategy

`app.js` still renders client-side views with simple route hashes.

The prototype chooses between API data and mock data through helper functions:

```text
getActiveProduct()
getActiveRepairPaths()
getActiveProviders()
getKnowledgeMetrics()
```

This keeps screens readable and avoids duplicating live/mock branches inside every component.

## Known limitations

- There is no real file upload yet.
- There is no authentication yet.
- There is no CSRF protection yet.
- The diagnosis engine is still a backend mock.
- The provider ranking is not a true Trust Engine yet.
- The prototype intentionally does not process payments.
