# Ownership & Dashboard QA Checklist

## Required checks

- Login as `repair.user@reborn.local` works.
- Creating a repair case returns `owner_id = user-demo-repair`.
- Repair user list returns `scope = owned`.
- Repair user dashboard returns role `repair_user`.
- Admin dashboard returns platform metrics.
- Admin can preview maker dashboard.
- Provider dashboard returns candidate jobs.
- Provider cannot create repair cases in MVP policy.
- Domain events still work after Step 8 timestamp fix.

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-ownership-dashboards.ps1
```
