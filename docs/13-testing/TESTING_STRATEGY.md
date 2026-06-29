# Testing Strategy

## Test levels

### Domain tests

Validate entities, value objects and domain rules.

### Application tests

Validate use cases such as creating repair cases and selecting repair paths.

### Integration tests

Validate repositories, database interactions and external provider boundaries.

### UI tests

Validate critical repair flows.

---

## Critical scenarios

- create repair case;
- upload valid/invalid files;
- classify repair case;
- recommend path;
- request provider quote;
- upload model;
- submit outcome;
- update trust signal.

---

## Quality principle

Test the repair journey first. Secondary marketplace features come later.
