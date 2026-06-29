# API Integration QA Checklist

## Local prerequisites

```powershell
php scripts/doctor.php
php scripts/setup-dev.php
php -S 127.0.0.1:8080 -t public public/index.php
```

## Manual browser QA

Open:

```text
http://127.0.0.1:8080/prototype/index.html
```

Check:

- [ ] API banner says `Live API`.
- [ ] Overview loads without console errors.
- [ ] `Create live API case` creates a case and moves to capture.
- [ ] Intake form creates a case.
- [ ] `Run live diagnosis` updates the diagnosis screen.
- [ ] Repair paths show API-generated values after diagnosis.
- [ ] Providers show records seeded in SQLite.
- [ ] Admin/Ops shows API-derived graph/provider counts.
- [ ] Refresh API button works.

## Mock fallback QA

Open the file directly:

```text
public/prototype/index.html
```

Check:

- [ ] API banner says `Mock mode`.
- [ ] The prototype remains navigable.
- [ ] Repair flow still works with local mock data.
- [ ] Buttons that require backend explain that the API is not live.

## API smoke test

```powershell
Invoke-RestMethod http://127.0.0.1:8080/api/health
Invoke-RestMethod http://127.0.0.1:8080/api/v1/providers
Invoke-RestMethod http://127.0.0.1:8080/api/v1/knowledge/nodes
```

Create a case:

```powershell
$case = Invoke-RestMethod `
  -Method Post `
  -Uri http://127.0.0.1:8080/api/v1/repair-cases `
  -ContentType 'application/json' `
  -Body '{"title":"Dishwasher wheel","description":"Broken lower basket wheel","category":"home_appliance"}'

$case.repair_case.id
```

Diagnose:

```powershell
Invoke-RestMethod `
  -Method Post `
  -Uri "http://127.0.0.1:8080/api/v1/repair-cases/$($case.repair_case.id)/diagnose"
```
