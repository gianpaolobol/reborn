<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Application\LoginUserService;
use Reborn\Identity\Application\PasswordHasher;
use Reborn\Identity\Application\RegisterUserService;
use Reborn\Identity\Application\TokenFactory;
use Reborn\Identity\Domain\User;
use Reborn\Identity\Infrastructure\SqliteAuthSessionRepository;
use Reborn\Identity\Infrastructure\SqliteUserRepository;
use Reborn\Shared\Database\Connection;
use Reborn\Shared\Database\MigrationRunner;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Support\Env;

$root = dirname(__DIR__);
Env::load($root . '/.env');
$databaseConfig = require $root . '/config/database.php';
$authConfig = require $root . '/config/auth.php';
$pdo = (new Connection($databaseConfig))->pdo();
(new MigrationRunner($pdo, $root . '/database/migrations'))->run();
foreach (glob($root . '/database/seeds/*.sql') ?: [] as $seedFile) {
    $sql = file_get_contents($seedFile);
    if ($sql !== false) {
        $pdo->exec($sql);
    }
}

$users = new SqliteUserRepository($pdo);
$sessions = new SqliteAuthSessionRepository($pdo);
$tokens = new TokenFactory();
$passwords = new PasswordHasher();
$events = new EventBus($pdo);
$login = new LoginUserService($users, $sessions, $passwords, $tokens, $events, $authConfig);
$register = new RegisterUserService($users, $sessions, $passwords, $tokens, $events, $authConfig);
$auth = new AuthContext($users, $sessions, $tokens);

$result = $login->handle(['email' => 'admin@reborn.local', 'password' => 'password'], '127.0.0.1', 'identity-test');
assert($result->user->role === User::ROLE_ADMIN);
assert(str_starts_with($result->plainTextToken, 'rbn_'));

echo "Admin login: ok\n";

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $result->plainTextToken;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/me';
$request = Request::fromGlobals();
$current = $auth->user($request);
assert($current->id === $result->user->id);

echo "Bearer authentication: ok\n";

$userLogin = $login->handle(['email' => 'repair.user@reborn.local', 'password' => 'password'], '127.0.0.1', 'identity-test');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $userLogin->plainTextToken;
$request = Request::fromGlobals();
try {
    $auth->requireRole($request, [User::ROLE_ADMIN]);
    throw new RuntimeException('Repair user should not pass admin authorization.');
} catch (ForbiddenException) {
    echo "Role authorization: ok\n";
}

$email = 'test+' . bin2hex(random_bytes(4)) . '@reborn.local';
$registered = $register->handle([
    'name' => 'Automated Test Maker',
    'email' => $email,
    'password' => 'password123',
    'role' => User::ROLE_MAKER,
], '127.0.0.1', 'identity-test');
assert($registered->user->role === User::ROLE_MAKER);

echo "Registration: ok\n";
echo "Identity tests passed.\n";
