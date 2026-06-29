# Step 8 Package Manifest

## Added files

- `config/auth.php`
- `database/migrations/003_identity_access_mvp.sql`
- `database/seeds/002_identity_seed.sql`
- `src/Identity/Domain/User.php`
- `src/Identity/Domain/AuthSession.php`
- `src/Identity/Domain/UserRepository.php`
- `src/Identity/Domain/AuthSessionRepository.php`
- `src/Identity/Domain/UserRegistered.php`
- `src/Identity/Domain/UserLoggedIn.php`
- `src/Identity/Application/AuthContext.php`
- `src/Identity/Application/AuthResult.php`
- `src/Identity/Application/LoginUserService.php`
- `src/Identity/Application/RegisterUserService.php`
- `src/Identity/Application/PasswordHasher.php`
- `src/Identity/Application/TokenFactory.php`
- `src/Identity/Infrastructure/SqliteUserRepository.php`
- `src/Identity/Infrastructure/SqliteAuthSessionRepository.php`
- `src/Identity/Presentation/AuthController.php`
- `src/Shared/Http/UnauthorizedException.php`
- `src/Shared/Http/ForbiddenException.php`
- `src/Shared/Http/ConflictException.php`
- `scripts/run-identity-tests.php`
- `scripts/smoke-identity-access.ps1`

## Modified files

- `bootstrap/app.php`
- `config/routes.php`
- `src/Shared/Http/Request.php`
- `.env.example`
- `composer.json`
- `README.md`
- `public/prototype/assets/js/api-client.js`
- `public/prototype/assets/js/state.js`
