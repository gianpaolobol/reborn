<?php

declare(strict_types=1);

namespace Reborn\Repair\Presentation;

use Reborn\Repair\Application\CreateRepairCaseService;
use Reborn\Repair\Application\DiagnoseRepairCaseService;
use Reborn\Repair\Application\GetRepairCaseService;
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
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return JsonResponse::ok(['repair_cases' => $this->listRepairCases->handle(50)]);
    }

    public function show(Request $request): JsonResponse
    {
        $case = $this->getRepairCase->handle((string) $request->param('id'));
        if ($case === null) {
            return JsonResponse::notFound('Repair case not found.');
        }

        return JsonResponse::ok(['repair_case' => $case]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->body();
        $errors = Validator::required($data, ['title', 'description', 'category']);

        if ($errors !== []) {
            return JsonResponse::validation($errors);
        }

        $case = $this->createRepairCase->handle([
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'category' => trim((string) $data['category']),
        ]);

        return JsonResponse::created(['repair_case' => $case]);
    }

    public function diagnose(Request $request): JsonResponse
    {
        $result = $this->diagnoseRepairCase->handle((string) $request->param('id'));
        if ($result === null) {
            return JsonResponse::notFound('Repair case not found.');
        }

        return JsonResponse::ok($result);
    }
}
