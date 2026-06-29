<?php

declare(strict_types=1);

namespace Reborn\Platform\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Platform\Application\ProductionReadinessService;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class PlatformController
{
    public function __construct(
        private readonly ProductionReadinessService $readiness,
        private readonly AuthContext $auth,
    ) {
    }

    public function ready(Request $request): JsonResponse
    {
        $readiness = $this->readiness->readiness();
        return JsonResponse::ok(['readiness' => $readiness], $request->requestId());
    }

    public function securityPolicy(Request $request): JsonResponse
    {
        return JsonResponse::ok(['security_policy' => $this->readiness->securityPolicy()], $request->requestId());
    }

    public function deployChecklist(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['deploy_checklist' => $this->readiness->deployChecklist()], $request->requestId());
    }

    public function runtime(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['runtime' => $this->readiness->runtimeReport()], $request->requestId());
    }

    public function storeReadinessSnapshot(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $snapshot = $this->readiness->recordSnapshot($user->id);
        return JsonResponse::created(['readiness_snapshot' => $snapshot], $request->requestId());
    }
}
