# Prototype Upload & Recognition UI

## Purpose

The Step 11 prototype update makes the repair intake tangible. Users can attach evidence to a repair case and ask Re-born for a preliminary AI repair diagnosis.

The UX principle remains: the user is not searching for an STL; the user wants the object to work again.

## Main screen

Route:

```text
/prototype/index.html#/capture
```

Title:

```text
Upload photos for repair diagnosis
```

Subtitle:

```text
Add photos, manuals or CAD files so Re-born can understand what needs to be repaired.
```

## Live API mode

When the PHP API is live:

1. Guest users are asked to log in.
2. Authenticated users must create or select a repair case.
3. The upload box accepts images, PDFs and CAD/model evidence.
4. Selected files are previewed locally.
5. Uploaded files are listed from the API.
6. `Run AI recognition` calls the recognition job endpoint.
7. The result panel shows object guess, damage assessment and recommended next step.
8. The diagnosis timeline advances through five stages.

## Mock fallback mode

When opened without the backend, the screen keeps the same product story but uses local staged files and a mock recognition result.

## JS client methods

Added to `api-client.js`:

- `uploadRepairAttachment(caseId, file, kind)`
- `getRepairAttachments(caseId)`
- `requestRecognition(caseId, attachmentIds)`
- `getRecognitionJobs(caseId)`
- `getRecognitionJob(jobId)`

## State additions

Added to `state.js`:

- `selectedUploadFiles`
- `api.repairAttachments`
- `api.recognitionJobs`
- `api.recognitionJob`

## UI components

Added CSS for:

- selected file preview cards
- uploaded attachment rows
- diagnosis timeline completion states
- recognition result panel
- MVP guardrail notices

## Manual QA

1. Start PHP server.
2. Open `http://127.0.0.1:8080/prototype/index.html#/login`.
3. Login as `repair.user@reborn.local` / `password`.
4. Create a repair case.
5. Go to the capture/upload screen.
6. Select a PNG/JPEG.
7. Upload selected files.
8. Confirm the attachment appears.
9. Run AI recognition.
10. Confirm the result panel and timeline update.
