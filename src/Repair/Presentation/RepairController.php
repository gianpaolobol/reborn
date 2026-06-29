<?php

declare(strict_types=1);

namespace Reborn\Repair\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Repair\Application\AddRepairAttachmentService;
use Reborn\Repair\Application\CreateRepairCaseService;
use Reborn\Repair\Application\DiagnoseRepairCaseService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\ListRepairAttachmentsService;
use Reborn\Repair\Application\ListRepairCasesService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Support\Validator;

final class RepairController
{
    public function __construct(
        private readonly ListRepairCasesService $listRepairCases,
        private readonly GetRepairCaseService $getRepairCase,
        private readonly CreateRepairCaseService $createRepairCase,
        private readonly DiagnoseRepairCaseService $diagnoseRepairCase,
        private readonly AddRepairAttachmentService $addRepairAttachment,
        private readonly ListRepairAttachmentsService $listRepairAttachments,
        private readonly AuthContext $auth,
        private readonly RepairCaseAccessPolicy $accessPolicy,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        $ownerId = $this->accessPolicy->ownerScopeForList($user);

        return JsonResponse::ok([
            'scope' => $ownerId === null ? 'role_visible' : 'owned',
            'repair_cases' => $this->listRepairCases->handle($limit, $ownerId),
        ], $request->requestId());
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $caseObject = $this->getRepairCase->find((string) $request->param('id'));
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this repair case.');
        }

        return JsonResponse::ok(['repair_case' => $caseObject->toArray()], $request->requestId());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        if (!$this->accessPolicy->canCreate($user)) {
            throw new ForbiddenException('This role cannot create repair cases.');
        }

        $data = $request->body();
        $errors = Validator::repairCasePayload($data);

        if ($errors !== []) {
            return JsonResponse::validation($errors, $request->requestId());
        }

        $case = $this->createRepairCase->handle([
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'category' => trim((string) $data['category']),
        ], $user->id);

        return JsonResponse::created(['repair_case' => $case], $request->requestId());
    }

    public function diagnose(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $caseObject = $this->getRepairCase->find((string) $request->param('id'));
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        if (!$this->accessPolicy->canMutate($user, $caseObject)) {
            throw new ForbiddenException('You cannot diagnose this repair case.');
        }

        $result = $this->diagnoseRepairCase->handle((string) $request->param('id'));
        if ($result === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        return JsonResponse::ok($result, $request->requestId());
    }

    public function attachments(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $caseObject = $this->getRepairCase->find((string) $request->param('id'));
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view attachments for this repair case.');
        }

        $attachments = $this->listRepairAttachments->handle((string) $request->param('id'));
        return JsonResponse::ok(['attachments' => $attachments], $request->requestId());
    }

    public function attach(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $caseObject = $this->getRepairCase->find((string) $request->param('id'));
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        if (!$this->accessPolicy->canMutate($user, $caseObject)) {
            throw new ForbiddenException('You cannot add attachments to this repair case.');
        }

        $attachment = $this->addRepairAttachment->handle(
            (string) $request->param('id'),
            $request->file('file'),
            (string) $request->input('kind', 'repair_asset')
        );

        return JsonResponse::created(['attachment' => $attachment], $request->requestId());
    }
}
