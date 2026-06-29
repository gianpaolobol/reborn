<?php

declare(strict_types=1);

use Reborn\AI\Application\GetRecognitionJobService;
use Reborn\AI\Application\ListRecognitionJobsService;
use Reborn\AI\Application\RecognitionEngine;
use Reborn\AI\Application\RequestRecognitionJobService;
use Reborn\AI\Infrastructure\SqliteRecognitionJobRepository;
use Reborn\AI\Presentation\RecognitionJobController;
use Reborn\Dashboard\Application\UserDashboardService;
use Reborn\Dashboard\Presentation\DashboardController;
use Reborn\Fulfilment\Application\AcceptProviderFulfilmentService;
use Reborn\Fulfilment\Application\CreateRepairFulfilmentService;
use Reborn\Fulfilment\Application\GetRepairFulfilmentService;
use Reborn\Fulfilment\Application\ListRepairFulfilmentsService;
use Reborn\Fulfilment\Application\UpdateFulfilmentStatusService;
use Reborn\Fulfilment\Infrastructure\SqliteRepairFulfilmentRepository;
use Reborn\Fulfilment\Presentation\RepairFulfilmentController;
use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Application\LoginUserService;
use Reborn\Identity\Application\PasswordHasher;
use Reborn\Identity\Application\RegisterUserService;
use Reborn\Identity\Application\TokenFactory;
use Reborn\Identity\Infrastructure\SqliteAuthSessionRepository;
use Reborn\Identity\Infrastructure\SqliteUserRepository;
use Reborn\Identity\Presentation\AuthController;
use Reborn\Knowledge\Application\KnowledgeEngine;
use Reborn\Marketplace\Application\GetRepairPathDecisionService;
use Reborn\Marketplace\Application\ListRepairPathDecisionsService;
use Reborn\Marketplace\Application\RepairPathDecisionEngine;
use Reborn\Marketplace\Application\RepairPathDecisionService;
use Reborn\Marketplace\Application\ConfirmMockPaymentIntentService;
use Reborn\Marketplace\Application\CreatePaymentIntentService;
use Reborn\Marketplace\Application\CreateRepairOrderService;
use Reborn\Marketplace\Application\GetPaymentIntentService;
use Reborn\Marketplace\Application\GetRepairOrderService;
use Reborn\Marketplace\Application\ListPaymentIntentsService;
use Reborn\Marketplace\Application\ListRepairOrdersService;
use Reborn\Marketplace\Application\RepairOrderAssembler;
use Reborn\Marketplace\Application\RequestRepairPathDecisionService;
use Reborn\Marketplace\Infrastructure\SqliteRepairPathDecisionRepository;
use Reborn\Marketplace\Infrastructure\SqlitePaymentIntentRepository;
use Reborn\Marketplace\Infrastructure\SqliteRepairOrderRepository;
use Reborn\Marketplace\Presentation\RepairPathDecisionController;
use Reborn\Marketplace\Presentation\RepairOrderController;
use Reborn\Provider\Application\ProviderMatchingService;
use Reborn\Provider\Application\GetProviderMatchService;
use Reborn\Provider\Application\GetProviderQuoteRequestService;
use Reborn\Provider\Application\ListProviderMatchesService;
use Reborn\Provider\Application\ListProviderQuoteRequestsService;
use Reborn\Provider\Application\ProviderMatchEngine;
use Reborn\Provider\Application\ProviderQuoteEngine;
use Reborn\Provider\Application\RequestProviderMatchService;
use Reborn\Provider\Application\RequestProviderQuoteService;
use Reborn\Provider\Infrastructure\SqliteProviderMatchRepository;
use Reborn\Provider\Infrastructure\SqliteProviderQuoteRequestRepository;
use Reborn\Provider\Presentation\ProviderMatchController;
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
$recognitionJobRepository = new SqliteRecognitionJobRepository($pdo);
$repairPathDecisionRepository = new SqliteRepairPathDecisionRepository($pdo);
$providerMatchRepository = new SqliteProviderMatchRepository($pdo);
$providerQuoteRequestRepository = new SqliteProviderQuoteRequestRepository($pdo);
$repairOrderRepository = new SqliteRepairOrderRepository($pdo);
$paymentIntentRepository = new SqlitePaymentIntentRepository($pdo);
$repairFulfilmentRepository = new SqliteRepairFulfilmentRepository($pdo);
$knowledgeEngine = new KnowledgeEngine($pdo);
$recognitionEngine = new RecognitionEngine($knowledgeEngine);
$decisionService = new RepairPathDecisionService($pdo);
$providerMatchingService = new ProviderMatchingService($pdo);
$fileStorage = new LocalFileStorage(dirname(__DIR__) . '/storage/uploads');

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


$recognitionJobController = new RecognitionJobController(
    new RequestRecognitionJobService($repairRepository, $attachmentRepository, $recognitionJobRepository, $eventBus),
    new ListRecognitionJobsService($repairRepository, $recognitionJobRepository),
    new GetRecognitionJobService($recognitionJobRepository),
    new GetRepairCaseService($repairRepository),
    $authContext,
    new RepairCaseAccessPolicy()
);

$repairPathDecisionController = new RepairPathDecisionController(
    new RequestRepairPathDecisionService(
        $repairRepository,
        $recognitionJobRepository,
        $repairPathDecisionRepository,
        new RepairPathDecisionEngine(),
        $eventBus,
        $pdo
    ),
    new ListRepairPathDecisionsService($repairRepository, $repairPathDecisionRepository),
    new GetRepairPathDecisionService($repairPathDecisionRepository),
    new GetRepairCaseService($repairRepository),
    $authContext,
    new RepairCaseAccessPolicy()
);

$providerMatchController = new ProviderMatchController(
    new RequestProviderMatchService(
        $repairRepository,
        $repairPathDecisionRepository,
        $providerMatchRepository,
        new ProviderMatchEngine($pdo),
        $eventBus
    ),
    new ListProviderMatchesService($repairRepository, $providerMatchRepository),
    new GetProviderMatchService($providerMatchRepository),
    new RequestProviderQuoteService(
        $providerMatchRepository,
        $providerQuoteRequestRepository,
        new ProviderQuoteEngine(),
        $eventBus
    ),
    new ListProviderQuoteRequestsService($repairRepository, $providerQuoteRequestRepository),
    new GetProviderQuoteRequestService($providerQuoteRequestRepository),
    new GetRepairCaseService($repairRepository),
    $authContext,
    new RepairCaseAccessPolicy()
);

$repairOrderController = new RepairOrderController(
    new CreateRepairOrderService(
        $providerQuoteRequestRepository,
        $repairOrderRepository,
        new RepairOrderAssembler(),
        $eventBus
    ),
    new ListRepairOrdersService($repairRepository, $repairOrderRepository),
    new GetRepairOrderService($repairOrderRepository),
    new CreatePaymentIntentService($repairOrderRepository, $paymentIntentRepository, $eventBus),
    new GetProviderQuoteRequestService($providerQuoteRequestRepository),
    new ListPaymentIntentsService($repairOrderRepository, $paymentIntentRepository),
    new GetPaymentIntentService($paymentIntentRepository),
    new ConfirmMockPaymentIntentService($paymentIntentRepository, $eventBus),
    new GetRepairCaseService($repairRepository),
    $authContext,
    new RepairCaseAccessPolicy()
);


$repairFulfilmentController = new RepairFulfilmentController(
    new CreateRepairFulfilmentService($repairOrderRepository, $paymentIntentRepository, $repairFulfilmentRepository, $eventBus),
    new ListRepairFulfilmentsService($repairFulfilmentRepository),
    new GetRepairFulfilmentService($repairFulfilmentRepository),
    new AcceptProviderFulfilmentService($repairFulfilmentRepository, $eventBus),
    new UpdateFulfilmentStatusService($repairFulfilmentRepository, $eventBus),
    new GetRepairOrderService($repairOrderRepository),
    new GetRepairCaseService($repairRepository),
    $authContext,
    new RepairCaseAccessPolicy()
);

$router = new Router();
(require dirname(__DIR__) . '/config/routes.php')($router, $repairController, $authController, $dashboardController, $recognitionJobController, $repairPathDecisionController, $providerMatchController, $repairOrderController, $repairFulfilmentController, $authContext, $pdo);

return [
    'router' => $router,
    'config' => $config,
];
