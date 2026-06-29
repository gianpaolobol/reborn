# Identity Access Implementation

## New bounded context implementation

The `Identity` bounded context now contains:

```text
src/Identity/Domain
src/Identity/Application
src/Identity/Infrastructure
src/Identity/Presentation
```

## Main classes

- `User`
- `AuthSession`
- `UserRepository`
- `AuthSessionRepository`
- `SqliteUserRepository`
- `SqliteAuthSessionRepository`
- `PasswordHasher`
- `TokenFactory`
- `RegisterUserService`
- `LoginUserService`
- `AuthContext`
- `AuthController`

## Architectural decision

Identity is implemented as an internal bounded context, not as an external SaaS dependency. This keeps the MVP locally runnable and allows the platform to evolve the role model around repair-specific actors.

Future options such as OAuth, passkeys or enterprise SSO should be added behind the Identity boundary without leaking into Repair, Provider or Marketplace domains.
