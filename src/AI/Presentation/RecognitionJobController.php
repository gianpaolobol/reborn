<?php

declare(strict_types=1);

namespace Reborn\AI\Presentation;

use Reborn\AI\Application\GetRecognitionJobService;
use Reborn\AI\Application\ListRecognitionJobsService;
use Reborn\AI\Application\RequestRecognitionJobService;
use Reborn\AI\Application\PhotoRecognitionGateway;
use Reborn\Identity\Application\AuthContext;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Http\ValidationException;

final class RecognitionJobController
{
    public function __construct(
        private readonly RequestRecognitionJobService $requestRecognitionJob,
        private readonly ListRecognitionJobsService $listRecognitionJobs,
        private readonly GetRecognitionJobService $getRecognitionJob,
        private readonly GetRepairCaseService $getRepairCase,
        private readonly AuthContext $auth,
        private readonly RepairCaseAccessPolicy $accessPolicy,
        private readonly PhotoRecognitionGateway $photoRecognitionGateway,
    ) {
    }


    public function providerStatus(Request $request): JsonResponse
    {
        $this->auth->user($request);

        return JsonResponse::ok([
            'photo_recognition_provider' => $this->photoRecognitionGateway->status(),
        ], $request->requestId());
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
            throw new ForbiddenException('You cannot view recognition jobs for this repair case.');
        }

        return JsonResponse::ok([
            'recognition_jobs' => $this->listRecognitionJobs->handle($repairCaseId),
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
            throw new ForbiddenException('You cannot request AI recognition for this repair case.');
        }

        $body = $request->body();
        $attachmentIds = $body['attachment_ids'] ?? null;
        if (!is_array($attachmentIds)) {
            throw new ValidationException(['attachment_ids' => ['attachment_ids must be an array.']]);
        }

        $job = $this->requestRecognitionJob->handle($repairCaseId, $user->id, $attachmentIds);

        return JsonResponse::created(['recognition_job' => $job], $request->requestId());
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $job = $this->getRecognitionJob->find((string) $request->param('id'));
        if ($job === null) {
            return JsonResponse::notFound('Recognition job not found.', $request->requestId());
        }

        $caseObject = $this->getRepairCase->find((string) $job['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for recognition job.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this recognition job.');
        }

        return JsonResponse::ok(['recognition_job' => $job], $request->requestId());
    }
}
