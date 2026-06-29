<?php

declare(strict_types=1);

namespace Reborn\Learning\Presentation;

use Reborn\Fulfilment\Application\GetRepairFulfilmentService;
use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Learning\Application\CreateCompletionReportService;
use Reborn\Learning\Application\GetCompletionReportService;
use Reborn\Learning\Application\GetLearningEventService;
use Reborn\Learning\Application\ListCompletionReportsService;
use Reborn\Learning\Application\ListLearningEventsService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class LearningController
{
    public function __construct(
        private readonly CreateCompletionReportService $createCompletionReport,
        private readonly ListCompletionReportsService $listCompletionReports,
        private readonly GetCompletionReportService $getCompletionReport,
        private readonly ListLearningEventsService $listLearningEvents,
        private readonly GetLearningEventService $getLearningEvent,
        private readonly GetRepairFulfilmentService $getFulfilment,
        private readonly GetRepairCaseService $getRepairCase,
        private readonly AuthContext $auth,
        private readonly RepairCaseAccessPolicy $accessPolicy,
    ) {
    }

    public function storeCompletionReport(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $this->requireProviderOperator($user);
        $fulfilment = $this->getFulfilment->find((string) $request->param('id'));
        if ($fulfilment === null) {
            return JsonResponse::notFound('Repair fulfilment not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $fulfilment['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for fulfilment.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot report completion for this fulfilment.');
        }

        return JsonResponse::created($this->createCompletionReport->handle((string) $fulfilment['id'], $user->id, $request->body()), $request->requestId());
    }

    public function completionReportsForFulfilment(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $fulfilment = $this->getFulfilment->find((string) $request->param('id'));
        if ($fulfilment === null) {
            return JsonResponse::notFound('Repair fulfilment not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $fulfilment['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for fulfilment.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view completion reports for this fulfilment.');
        }

        return JsonResponse::ok(['completion_reports' => $this->listCompletionReports->handle((string) $fulfilment['id'])], $request->requestId());
    }

    public function showCompletionReport(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $report = $this->getCompletionReport->find((string) $request->param('id'));
        if ($report === null) {
            return JsonResponse::notFound('Repair completion report not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $report['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for completion report.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this completion report.');
        }

        return JsonResponse::ok(['completion_report' => $report], $request->requestId());
    }

    public function learningEventsForCase(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $caseObject = $this->getRepairCase->find((string) $request->param('id'));
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view learning events for this repair case.');
        }

        return JsonResponse::ok(['learning_events' => $this->listLearningEvents->handle((string) $caseObject->id)], $request->requestId());
    }

    public function showLearningEvent(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $event = $this->getLearningEvent->find((string) $request->param('id'));
        if ($event === null) {
            return JsonResponse::notFound('Repair learning event not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $event['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for learning event.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this learning event.');
        }

        return JsonResponse::ok(['learning_event' => $event], $request->requestId());
    }

    private function requireProviderOperator(User $user): void
    {
        if (!$user->hasAnyRole([User::ROLE_PROVIDER, User::ROLE_ADMIN])) {
            throw new ForbiddenException('Only provider operators or admins can report repair completion.');
        }
    }
}
