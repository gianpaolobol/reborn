# Source Code Structure

Production code has not started yet.

When implementation begins, follow this modular monolith structure:

```text
src/
  Shared/
  Identity/
  Repair/
  AI/
  Marketplace/
  Provider/
  Knowledge/
  Wallet/
  Company/
  Admin/
```

Rules:

- one bounded context per top-level module;
- controllers are not domain objects;
- repositories are interfaces at application/domain boundary;
- infrastructure implementations live behind contracts;
- domain events cross boundaries, not direct table access.
