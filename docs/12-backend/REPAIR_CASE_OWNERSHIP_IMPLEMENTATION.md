# Repair Case Ownership Implementation

Step 9 wires Identity into the Repair domain without introducing a framework.

## Key classes

```text
src/Repair/Application/RepairCaseAccessPolicy.php
src/Dashboard/Application/UserDashboardService.php
src/Dashboard/Presentation/DashboardController.php
```

## Design rules

- The controller authenticates the request through `AuthContext`.
- The access policy decides whether the role can create, view or mutate a repair case.
- The repository remains storage-focused and does not know about HTTP or tokens.
- Dashboards are read models built with direct SQL queries for MVP speed.

## Why this matters

The platform must not feel like a generic marketplace. Ownership keeps the user journey centred on the user's object and repair outcome, while dashboards give makers, providers and admins the operational views they need to create liquidity around repairs.
