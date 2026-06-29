# Prototype Repair Path Decision UI

Step 12 updates the prototype so that the Repair Journey can move from evidence and AI recognition to ranked repair paths.

## Updated screens

### `#/capture`

After upload and AI recognition, the screen now shows a `Repair Path Decision Engine` panel.

Actions:

- generate repair paths from the active recognition job;
- re-run the decision;
- open the repair paths screen.

### `#/repair-paths`

The repair paths screen now displays:

- latest decision context;
- recommended path;
- ranked path cards;
- re-run decision control.

## Live API mode

The prototype calls:

- `POST /api/v1/repair-cases/{id}/repair-path-decisions`
- `GET /api/v1/repair-cases/{id}/repair-path-decisions`
- `GET /api/v1/repair-paths?case_id={id}`

The Bearer token from Step 10 is reused.

## Mock fallback mode

If the prototype is opened without the PHP server, the UI creates a local mock decision with ranked paths. This keeps the UX demonstrable without changing the backend contract.

## UX principle

The UI text intentionally avoids marketplace-first language. The user is not choosing a file. The user is choosing the best way to make the object work again.
