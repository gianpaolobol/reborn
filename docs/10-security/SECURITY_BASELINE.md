# Security Baseline

## Principles

- Do not trust user uploads.
- Do not expose uploaded files directly without validation.
- Do not store secrets in repository.
- Do not mix admin and public actions.
- Log security-relevant events.
- Validate file type, size and content.
- Use CSRF protection for web forms.
- Use prepared statements for database queries.
- Hash passwords with modern password hashing.
- Rate-limit sensitive actions.

---

## Upload security

Repair photos and CAD files are core to Re-born but also high-risk.

Required controls:

- allowed extensions;
- MIME validation;
- max file size;
- randomized storage names;
- no executable uploads;
- separate public/private paths;
- virus/malware scanning in production;
- image processing safety;
- CAD/STL validation.

---

## AI safety

AI suggestions must be treated as unverified unless confirmed.

Safety-critical parts should require warnings or exclusion rules.

Examples:

- automotive load-bearing parts;
- medical devices;
- electrical safety components;
- child safety equipment;
- pressure vessels;
- structural components.

---

## Privacy

Re-born may receive images of private objects, homes, labels or serial numbers.

The UX must explain:

- what is uploaded;
- why it is needed;
- how it is used;
- whether it contributes to learning;
- how users can delete data.
