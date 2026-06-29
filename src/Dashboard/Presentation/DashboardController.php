<?php

declare(strict_types=1);

namespace Reborn\Dashboard\Presentation;

use Reborn\Dashboard\Application\UserDashboardService;
use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class DashboardController
{
    public function __construct(
        private readonly AuthContext $auth,
        private readonly UserDashboardService $dashboards,
    ) {
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        return JsonResponse::ok([
            'user' => $user->toArray(),
            'dashboard' => $this->dashboards->forUser($user),
        ], $request->requestId());
    }

    public function repairUser(Request $request): JsonResponse
    {
        return $this->roleDashboard($request, User::ROLE_REPAIR_USER);
    }

    public function maker(Request $request): JsonResponse
    {
        return $this->roleDashboard($request, User::ROLE_MAKER);
    }

    public function provider(Request $request): JsonResponse
    {
        return $this->roleDashboard($request, User::ROLE_PROVIDER);
    }

    public function enterprise(Request $request): JsonResponse
    {
        return $this->roleDashboard($request, User::ROLE_ENTERPRISE);
    }

    public function admin(Request $request): JsonResponse
    {
        return $this->roleDashboard($request, User::ROLE_ADMIN);
    }

    private function roleDashboard(Request $request, string $role): JsonResponse
    {
        $user = $this->auth->requireRole($request, [$role, User::ROLE_ADMIN]);

        return JsonResponse::ok([
            'requested_role' => $role,
            'user' => $user->toArray(),
            'dashboard' => $this->dashboards->forRole($role, $user),
        ], $request->requestId());
    }
}
