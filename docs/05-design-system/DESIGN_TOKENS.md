# Design Tokens

## Color palette

```css
:root {
  --color-graphite-900: #111315;
  --color-graphite-800: #1B1F23;
  --color-graphite-700: #2A3036;
  --color-off-white: #F6F4EF;
  --color-paper: #FFFFFF;
  --color-repair-green: #32D583;
  --color-electric-blue: #2F80ED;
  --color-safety-orange: #FF7A1A;
  --color-border: #D8D6D0;
  --color-muted: #6B7280;
  --color-danger: #D92D20;
}
```

---

## Radius

```css
:root {
  --radius-xs: 2px;
  --radius-sm: 4px;
  --radius-md: 4px;
  --radius-lg: 4px;
}
```

Border radius must not exceed 4px in the core product UI.

---

## Typography direction

The product should use clean, technical and readable typography.

Suggested roles:

- titles: strong geometric/technical sans;
- body: highly readable sans;
- data labels: compact, precise sans.

Avoid decorative startup fonts that reduce trust.

---

## Spacing scale

```css
:root {
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 24px;
  --space-6: 32px;
  --space-7: 48px;
  --space-8: 64px;
}
```

---

## UI mood

- precise;
- minimal;
- technical;
- repair-oriented;
- not playful;
- not generic SaaS;
- not glossy.
