<?php

declare(strict_types=1);

namespace Reborn\Repair\Presentation;

use Reborn\Repair\Application\AddRepairAttachmentService;
use Reborn\Repair\Application\CreateRepairCaseService;
use Reborn\Repair\Application\DiagnoseRepairCaseService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\ListRepairAttachmentsService;
use Reborn\Repair\Application\ListRepairCasesService;
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
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        return JsonResponse::ok(['repair_cases' => $this->listRepairCases->handle($limit)], $request->requestId());
    }

    public function show(Request $request): JsonResponse
    {
        $case = $this->getRepairCase->handle((string) $request->param('id'));
        if ($case === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        return JsonResponse::ok(['repair_case' => $case], $request->requestId());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->body();
        $errors = Validator::repairCasePayload($data);

        if ($errors !== []) {
            return JsonResponse::validation($errors, $request->requestId());
        }

        $case = $this->createRepairCase->handle([
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'category' => trim((string) $data['category']),
        ]);

        return JsonResponse::created(['repair_case' => $case], $request->requestId());
    }

    public function diagnose(Request $request): JsonResponse
    {
        $result = $this->diagnoseRepairCase->handle((string) $request->param('id'));
        if ($result === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        return JsonResponse::ok($result, $request->requestId());
    }

    public function attachments(Request $request): JsonResponse
    {
        $attachments = $this->listRepairAttachments->handle((string) $request->param('id'));
        return JsonResponse::ok(['attachments' => $attachments], $request->requestId());
    }

    public function attach(Request $request): JsonResponse
    {
        $attachment = $this->addRepairAttachment->handle(
            (string) $request->param('id'),
            $request->file('file'),
            (string) $request->input('kind', 'repair_asset')
        );

        return JsonResponse::created(['attachment' => $attachment], $request->requestId());
    }
}
