# Static Prototype Implementation Notes

## Stack

The Step 4 prototype uses:

```text
HTML5
CSS3
Vanilla JavaScript
Hash routing
Static mock data
```

## Main files

```text
public/prototype/index.html
public/prototype/assets/css/reborn.css
public/prototype/assets/js/prototype-data.js
public/prototype/assets/js/state.js
public/prototype/assets/js/app.js
```

## Why hash routing

Hash routing allows the prototype to run locally by double-clicking `index.html`, without a server and without routing configuration.

## Why no framework

The MVP technical constraint is Vanilla JavaScript. Step 4 respects this constraint so that future frontend implementation does not inherit a prototype dependency that violates the product architecture.

## Component candidates extracted from the prototype

| Component | Purpose |
|---|---|
| Topbar | Global navigation and brand lockup |
| Stepper | Repair journey progress |
| Repair intake form | Start a repair request |
| Dropzone | Photo/file evidence collection |
| Recognition result panel | AI diagnosis output |
| Repair path card | Decision Engine option |
| Provider card | Provider ranking and quote |
| Wallet/impact metric | Credits and sustainability feedback |
| Timeline row | Explain engine events |
| Badge | Trust, risk and status labels |

## Production caution

The prototype uses inline event handlers for speed and clarity. Production frontend code should move toward delegated events or modular JS files once the first implementation begins.
