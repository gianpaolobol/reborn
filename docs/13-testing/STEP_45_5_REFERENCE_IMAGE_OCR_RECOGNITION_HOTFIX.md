# Step 45.5 — Reference Image OCR Recognition Hotfix

## Why

Real testing showed that product-detail/reference images containing clear text such as part number, part name and dimensions could still be handled as if the uploaded photo was insufficient. This was wrong for the Re-born flow: a user may upload a product listing, reference image or dimension diagram to explain the replacement part they need.

## Fix

- OpenAI prompt now explicitly accepts:
  - real broken-part photos;
  - product listing/reference images;
  - product detail graphics;
  - dimension diagrams;
  - mixed image evidence.
- Visible text, part numbers, callouts and dimensions are treated as primary recognition evidence.
- The response schema includes `identification` and `part_spec` metadata.
- Reference images with readable product identity can be marked as recognized.
- The UI displays visible text, part number, known dimensions and key features in Italian.
- The same main CTA still handles follow-up uploads; no extra button is introduced.
- The debug script accepts one or multiple `-ImagePath` values.

## Expected outcome for dishwasher rack wheel reference images

The AI should identify something equivalent to:

- pezzo: ruota cestello inferiore lavastoviglie;
- codice pezzo: 165314, if visible;
- dimensioni lette: circa 25 mm height and 35.8 mm diameter, if visible;
- next step: maker brief / validation, not generic unknown component.

## Guardrail

Even when recognized, Re-born must not approve production automatically. It can proceed to a maker-ready brief only after dimensional, material and fit validation.
