<?php

declare(strict_types=1);

use Reborn\AI\Application\RecognitionEngine;
use Reborn\Dashboard\Application\UserDashboardService;
use Reborn\Dashboard\Presentation\DashboardController;
use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Application\LoginUserService;
use Reborn\Identity\Application\PasswordHasher;
use Reborn\Identity\Application\RegisterUserService;
use Reborn\Identity\Application\TokenFactory;
use Reborn\Identity\Infrastructure\SqliteAuthSessionRepository;
use Reborn\Identity\Infrastructure\SqliteUserRepository;
use Reborn\Identity\Presentation\AuthController;
use Reborn\Knowledge\Application\KnowledgeEngine;
use Reborn\Marketplace\Application\RepairPathDecisionService;
use Reborn\Provider\Application\ProviderMatchingService;
use Reborn\Repair\Application\AddRepairAttachmentService;
use Reborn\Repair\Application\CreateRepairCaseService;
use Reborn\Repair\Application\DiagnoseRepairCaseService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\ListRepairAttachmentsService;
use Reborn\Repair\Application\ListRepairCasesService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Repair\Infrastructure\SqliteRepairAttachmentRepository;
use Reborn\Repair\Infrastructure\SqliteRepairCaseRepository;
use Reborn\Repair\Presentation\RepairController;
use Reborn\Shared\Database\Connection;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\Router;
use Reborn\Shared\Storage\LocalFileStorage;
use Reborn\Shared\Support\Env;

require_once __DIR__ . '/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$config = [
    'app' => require dirname(__DIR__) . '/config/app.php',
    'database' => require dirname(__DIR__) . '/config/database.php',
    'auth' => require dirname(__DIR__) . '/config/auth.php',
];

$connection = new Connection($config['database']);
$pdo = $connection->pdo();
$eventBus = new EventBus($pdo);

$userRepository = new SqliteUserRepository($pdo);
$sessionRepository = new SqliteAuthSessionRepository($pdo);
$tokenFactory = new TokenFactory();
$passwordHasher = new PasswordHasher();
$authContext = new AuthContext($userRepository, $sessionRepository, $tokenFactory);
$authController = new AuthController(
    new RegisterUserService($userRepository, $sessionRepository, $passwordHasher, $tokenFactory, $eventBus, $config['auth']),
    new LoginUserService($userRepository, $sessionRepository, $passwordHasher, $tokenFactory, $eventBus, $config['auth']),
    $authContext
);

$dashboardController = new DashboardController(
    $authContext,
    new UserDashboardService($pdo)
);

$repairRepository = new SqliteRepairCaseRepository($pdo);
$attachmentRepository = new SqliteRepairAttachmentRepository($pdo);
$knowledgeEngine = new KnowledgeEngine($pdo);
$recognitionEngine = new RecognitionEngine($knowledgeEngine);
$decisionService = new RepairPathDecisionService($pdo);
$providerMatchingService = new ProviderMatchingService($pdo);
$fileStorage = new LocalFileStorage(dirname(__DIR__) . '/storage/app/uploads');

$repairController = new RepairController(
    new ListRepairCasesService($repairRepository),
    new GetRepairCaseService($repairRepository),
    new CreateRepairCaseService($repairRepository, $eventBus),
    new DiagnoseRepairCaseService(
        $repairRepository,
        $recognitionEngine,
        $decisionService,
        $providerMatchingService,
        $eventBus
    ),
    new AddRepairAttachmentService($repairRepository, $attachmentRepository, $fileStorage, $eventBus),
    new ListRepairAttachmentsService($repairRepository, $attachmentRepository),
    $authContext,
    new RepairCaseAccessPolicy()
);

$router = new Router();
(require dirname(__DIR__) . '/config/routes.php')($router, $repairController, $authController, $dashboardController, $authContext, $pdo);

return [
    'router' => $router,
    'config' => $config,
];
