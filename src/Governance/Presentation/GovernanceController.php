<?php

declare(strict_types=1);

namespace Reborn\Governance\Presentation;

use Reborn\Governance\Application\CreateProviderRankingSnapshotService;
use Reborn\Governance\Application\GovernanceSummaryService;
use Reborn\Governance\Application\ListGovernanceActionsService;
use Reborn\Governance\Application\ListProviderRankingsService;
use Reborn\Governance\Application\MarketplaceGovernancePolicy;
use Reborn\Governance\Application\RecordProviderGovernanceActionService;
use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class GovernanceController
{
    public function __construct(
        private readonly CreateProviderRankingSnapshotService $createRankingSnapshot,
        private readonly ListProviderRankingsService $listProviderRankings,
        private readonly RecordProviderGovernanceActionService $recordProviderAction,
        private readonly ListGovernanceActionsService $listGovernanceActions,
        private readonly GovernanceSummaryService $summaryService,
        private readonly MarketplaceGovernancePolicy $policy,
        private readonly AuthContext $auth,
    ) {
    }

    public function createRankingSnapshot(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created($this->createRankingSnapshot->handle($user), $request->requestId());
    }

    public function latestRankingSnapshot(Request $request): JsonResponse
    {
        $this->auth->user($request);
        return JsonResponse::ok($this->listProviderRankings->handle(), $request->requestId());
    }

    public function providerRankings(Request $request): JsonResponse
    {
        $this->auth->user($request);
        return JsonResponse::ok($this->listProviderRankings->handle(), $request->requestId());
    }

    public function recordProviderAction(Request $request): JsonResponse
    {
        $user = $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        return JsonResponse::created($this->recordProviderAction->handle((string) $request->param('id'), $user, $request->body()), $request->requestId());
    }

    public function providerActions(Request $request): JsonResponse
    {
        $this->auth->user($request);
        $activeOnly = (string) $request->query('active_only', '') === '1';
        return JsonResponse::ok(['governance_actions' => $this->listGovernanceActions->forProvider((string) $request->param('id'), $activeOnly)], $request->requestId());
    }

    public function actions(Request $request): JsonResponse
    {
        $this->auth->requireRole($request, [User::ROLE_ADMIN]);
        $status = $request->query('status');
        return JsonResponse::ok(['governance_actions' => $this->listGovernanceActions->all($status !== null ? (string) $status : null)], $request->requestId());
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
