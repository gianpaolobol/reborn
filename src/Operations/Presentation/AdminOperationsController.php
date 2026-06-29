<?php

declare(strict_types=1);

namespace Reborn\Operations\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Operations\Application\AdminOperationsPolicy;
use Reborn\Operations\Application\AssignOpsReviewItemService;
use Reborn\Operations\Application\CreateOpsEscalationService;
use Reborn\Operations\Application\CreateOpsReviewItemService;
use Reborn\Operations\Application\GetOpsReviewItemService;
use Reborn\Operations\Application\ListOpsEscalationsService;
use Reborn\Operations\Application\ListOpsReviewItemsService;
use Reborn\Operations\Application\OpsSummaryService;
use Reborn\Operations\Application\RecordOpsModerationActionService;
use Reborn\Operations\Application\ResolveOpsReviewItemService;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class AdminOperationsController
{
    public function __construct(
        private readonly CreateOpsReviewItemService $createReviewItem,
        private readonly ListOpsReviewItemsService $listReviewItems,
        private readonly GetOpsReviewItemService $getReviewItem,
        private readonly AssignOpsReviewItemService $assignReviewItem,
        private readonly RecordOpsModerationActionService $recordModerationAction,
        private readonly CreateOpsEscalationService $createEscalation,
        private readonly ListOpsEscalationsService $listEscalations,
        private readonly ResolveOpsReviewItemService $resolveReviewItem,
        private readonly OpsSummaryService $summaryService,
        private readonly AdminOperationsPolicy $policy,
        private readonly AuthContext $auth,
    ) {
    }

    public function createReviewItem(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created($this->createReviewItem->handle($user, $request->body()), $request->requestId());
    }

    public function reviewItems(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = $request->query('status');
        $priority = $request->query('priority');
        return JsonResponse::ok($this->listReviewItems->handle($status !== null ? (string) $status : null, $priority !== null ? (string) $priority : null), $request->requestId());
    }

    public function reviewItem(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok($this->getReviewItem->handle((string) $request->param('id')), $request->requestId());
    }

    public function assignReviewItem(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok($this->assignReviewItem->handle((string) $request->param('id'), $user, $request->body()), $request->requestId());
    }

    public function recordModerationAction(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created($this->recordModerationAction->handle((string) $request->param('id'), $user, $request->body()), $request->requestId());
    }

    public function createEscalation(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created($this->createEscalation->handle((string) $request->param('id'), $user, $request->body()), $request->requestId());
    }

    public function escalations(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = $request->query('status');
        return JsonResponse::ok($this->listEscalations->handle($status !== null ? (string) $status : null), $request->requestId());
    }

    public function resolveReviewItem(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok($this->resolveReviewItem->handle((string) $request->param('id'), $user, $request->body()), $request->requestId());
    }

    public function summary(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok($this->summaryService->handle(), $request->requestId());
    }

    public function policies(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::ok(['policy' => $this->policy->toArray()], $request->requestId());
    }
}
