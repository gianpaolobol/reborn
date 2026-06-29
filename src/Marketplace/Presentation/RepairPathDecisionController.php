<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Marketplace\Application\GetRepairPathDecisionService;
use Reborn\Marketplace\Application\ListRepairPathDecisionsService;
use Reborn\Marketplace\Application\RequestRepairPathDecisionService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class RepairPathDecisionController
{
    public function __construct(
        private readonly RequestRepairPathDecisionService $requestDecision,
        private readonly ListRepairPathDecisionsService $listDecisions,
        private readonly GetRepairPathDecisionService $getDecision,
        private readonly GetRepairCaseService $getRepairCase,
        private readonly AuthContext $auth,
        private readonly RepairCaseAccessPolicy $accessPolicy,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $repairCaseId = (string) $request->param('id');
        $caseObject = $this->getRepairCase->find($repairCaseId);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view repair path decisions for this repair case.');
        }

        return JsonResponse::ok([
            'repair_path_decisions' => $this->listDecisions->handle($repairCaseId),
        ], $request->requestId());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $repairCaseId = (string) $request->param('id');
        $caseObject = $this->getRepairCase->find($repairCaseId);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        if (!$this->accessPolicy->canMutate($user, $caseObject)) {
            throw new ForbiddenException('You cannot request a repair path decision for this repair case.');
        }

        $body = $request->body();
        $recognitionJobId = isset($body['recognition_job_id']) && trim((string) $body['recognition_job_id']) !== ''
            ? trim((string) $body['recognition_job_id'])
            : null;

        $result = $this->requestDecision->handle($repairCaseId, $user->id, $recognitionJobId);

        return JsonResponse::created($result, $request->requestId());
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $decision = $this->getDecision->find((string) $request->param('id'));
        if ($decision === null) {
            return JsonResponse::notFound('Repair path decision not found.', $request->requestId());
        }

        $caseObject = $this->getRepairCase->find((string) $decision['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for repair path decision.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this repair path decision.');
        }

        return JsonResponse::ok(['repair_path_decision' => $decision], $request->requestId());
    }
}
