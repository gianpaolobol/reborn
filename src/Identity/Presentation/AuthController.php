<?php

declare(strict_types=1);

namespace Reborn\Identity\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Application\LoginUserService;
use Reborn\Identity\Application\RegisterUserService;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class AuthController
{
    public function __construct(
        private readonly RegisterUserService $registerUser,
        private readonly LoginUserService $loginUser,
        private readonly AuthContext $auth,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $result = $this->registerUser->handle($request->body(), $request->ipAddress(), $request->userAgent());
        return JsonResponse::created($result->toArray(), $request->requestId());
    }

    public function login(Request $request): JsonResponse
    {
        $result = $this->loginUser->handle($request->body(), $request->ipAddress(), $request->userAgent());
        return JsonResponse::ok($result->toArray(), $request->requestId());
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        return JsonResponse::ok(['user' => $user->toArray()], $request->requestId());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->revokeCurrentSession($request);
        return JsonResponse::ok(['logged_out' => true], $request->requestId());
    }
}
