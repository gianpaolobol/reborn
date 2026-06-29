<?php

declare(strict_types=1);

namespace Reborn\Fulfilment\Presentation;

use Reborn\Fulfilment\Application\AcceptProviderFulfilmentService;
use Reborn\Fulfilment\Application\CreateRepairFulfilmentService;
use Reborn\Fulfilment\Application\GetRepairFulfilmentService;
use Reborn\Fulfilment\Application\ListRepairFulfilmentsService;
use Reborn\Fulfilment\Application\UpdateFulfilmentStatusService;
use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Marketplace\Application\GetRepairOrderService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Http\ValidationException;

final class RepairFulfilmentController
{
    public function __construct(
        private readonly CreateRepairFulfilmentService $createFulfilment,
        private readonly ListRepairFulfilmentsService $listFulfilments,
        private readonly GetRepairFulfilmentService $getFulfilment,
        private readonly AcceptProviderFulfilmentService $acceptProvider,
        private readonly UpdateFulfilmentStatusService $updateStatus,
        private readonly GetRepairOrderService $getRepairOrder,
        private readonly GetRepairCaseService $getRepairCase,
        private readonly AuthContext $auth,
        private readonly RepairCaseAccessPolicy $accessPolicy,
    ) {
    }

    public function storeForOrder(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $order = $this->getRepairOrder->find((string) $request->param('id'));
        if ($order === null) {
            return JsonResponse::notFound('Repair order not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $order['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for repair order.', $request->requestId());
        }
        if (!$this->accessPolicy->canMutate($user, $caseObject)) {
            throw new ForbiddenException('You cannot create fulfilment for this repair order.');
        }

        return JsonResponse::created($this->createFulfilment->handle((string) $order['id'], $user->id), $request->requestId());
    }

    public function indexForOrder(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $order = $this->getRepairOrder->find((string) $request->param('id'));
        if ($order === null) {
            return JsonResponse::notFound('Repair order not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $order['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for repair order.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view fulfilments for this repair order.');
        }

        return JsonResponse::ok(['fulfilments' => $this->listFulfilments->handle((string) $order['id'])], $request->requestId());
    }

    public function show(Request $request): JsonResponse
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
            throw new ForbiddenException('You cannot view this repair fulfilment.');
        }

        return JsonResponse::ok(['fulfilment' => $fulfilment], $request->requestId());
    }

    public function acceptProvider(Request $request): JsonResponse
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
            throw new ForbiddenException('You cannot accept this repair fulfilment.');
        }

        $body = $request->body();
        $notes = isset($body['provider_notes']) ? trim((string) $body['provider_notes']) : null;

        return JsonResponse::ok($this->acceptProvider->handle((string) $fulfilment['id'], $user->id, $notes ?: null), $request->requestId());
    }

    public function updateStatus(Request $request): JsonResponse
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
            throw new ForbiddenException('You cannot update this repair fulfilment.');
        }

        $body = $request->body();
        $status = trim((string) ($body['status'] ?? ''));
        if ($status === '') {
            throw new ValidationException(['status' => ['status is required.']]);
        }
        $note = isset($body['note']) ? trim((string) $body['note']) : null;

        return JsonResponse::ok($this->updateStatus->handle((string) $fulfilment['id'], $status, $note ?: null, $user->id), $request->requestId());
    }

    private function requireProviderOperator(User $user): void
    {
        if (!$user->hasAnyRole([User::ROLE_PROVIDER, User::ROLE_ADMIN])) {
            throw new ForbiddenException('Only provider operators or admins can manage fulfilment execution.');
        }
    }
}
