# Repair Upload & Recognition QA Checklist

## Automated smoke test

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-repair-upload-recognition.ps1
```

Expected result:

```text
Repair upload and AI recognition smoke test passed.
```

## Backend checks

- `/api/health` returns `ok`.
- Login as `repair.user@reborn.local` works.
- Authenticated repair user can create a repair case.
- PNG upload returns an attachment id.
- Attachment list contains the uploaded attachment.
- Recognition job endpoint returns status `completed`.
- Recognition result contains:
  - `object_guess`
  - `damage_assessment`
  - `recommended_next_step`
- Recognition job list contains the created job.

## Domain event checks

Verify via admin token and `/api/v1/domain-events?limit=100`:

- `repair.attachment_added`
- `ai.recognition_requested`
- `ai.recognition_completed`

## Authorization checks

- Missing Bearer token is rejected.
- Provider cannot create repair cases.
- Upload requires mutation access to the repair case.
- Recognition request requires mutation access to the repair case.
- Recognition job detail requires view access to the linked repair case.

## File validation checks

Accepted MVP examples:

- `.jpg` / `image/jpeg`
- `.png` / `image/png`
- `.webp` / `image/webp`
- `.pdf` / `application/pdf`
- `.stl`, `.step`, `.stp`, `.obj` as `application/octet-stream` when applicable

Rejected examples:

- Empty file
- File larger than 15 MB
- Unsupported extension
- Unsupported MIME type

## Prototype checks

- `#/capture` shows login requirement when unauthenticated in live mode.
- After login and case creation, the upload panel appears.
- Selected image files show local previews.
- Uploaded files appear as API attachments.
- AI recognition result is displayed after the job completes.
- Diagnosis timeline marks completed steps.
- Mock fallback works without API.
