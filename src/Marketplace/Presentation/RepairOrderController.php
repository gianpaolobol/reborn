<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Marketplace\Application\ConfirmMockPaymentIntentService;
use Reborn\Marketplace\Application\CreatePaymentIntentService;
use Reborn\Marketplace\Application\CreateRepairOrderService;
use Reborn\Marketplace\Application\GetPaymentIntentService;
use Reborn\Marketplace\Application\GetRepairOrderService;
use Reborn\Marketplace\Application\ListPaymentIntentsService;
use Reborn\Marketplace\Application\ListRepairOrdersService;
use Reborn\Provider\Application\GetProviderQuoteRequestService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;

final class RepairOrderController
{
    public function __construct(
        private readonly CreateRepairOrderService $createRepairOrder,
        private readonly ListRepairOrdersService $listRepairOrders,
        private readonly GetRepairOrderService $getRepairOrder,
        private readonly CreatePaymentIntentService $createPaymentIntent,
        private readonly GetProviderQuoteRequestService $getQuoteRequest,
        private readonly ListPaymentIntentsService $listPaymentIntents,
        private readonly GetPaymentIntentService $getPaymentIntent,
        private readonly ConfirmMockPaymentIntentService $confirmMockPaymentIntent,
        private readonly GetRepairCaseService $getRepairCase,
        private readonly AuthContext $auth,
        private readonly RepairCaseAccessPolicy $accessPolicy,
    ) {
    }

    public function storeFromQuote(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $quoteRequestId = (string) $request->param('id');
        $quote = $this->getQuoteRequest->find($quoteRequestId);
        if ($quote === null) {
            return JsonResponse::notFound('Quote request not found.', $request->requestId());
        }

        $caseObject = $this->getRepairCase->find((string) $quote['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for quote request.', $request->requestId());
        }
        if (!$this->accessPolicy->canMutate($user, $caseObject)) {
            throw new ForbiddenException('You cannot create a repair order for this quote.');
        }

        return JsonResponse::created($this->createRepairOrder->handle($quoteRequestId, $user->id), $request->requestId());
    }

    public function indexForCase(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $repairCaseId = (string) $request->param('id');
        $caseObject = $this->getRepairCase->find($repairCaseId);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view repair orders for this repair case.');
        }

        return JsonResponse::ok(['repair_orders' => $this->listRepairOrders->handle($repairCaseId)], $request->requestId());
    }

    public function show(Request $request): JsonResponse
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
            throw new ForbiddenException('You cannot view this repair order.');
        }

        return JsonResponse::ok(['repair_order' => $order], $request->requestId());
    }

    public function storePaymentIntent(Request $request): JsonResponse
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
            throw new ForbiddenException('You cannot create a payment intent for this repair order.');
        }

        return JsonResponse::created($this->createPaymentIntent->handle((string) $order['id'], $user->id), $request->requestId());
    }

    public function paymentIntents(Request $request): JsonResponse
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
            throw new ForbiddenException('You cannot view payment intents for this repair order.');
        }

        return JsonResponse::ok(['payment_intents' => $this->listPaymentIntents->handle((string) $order['id'])], $request->requestId());
    }

    public function showPaymentIntent(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $intent = $this->getPaymentIntent->find((string) $request->param('id'));
        if ($intent === null) {
            return JsonResponse::notFound('Payment intent not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $intent['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for payment intent.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this payment intent.');
        }

        return JsonResponse::ok(['payment_intent' => $intent], $request->requestId());
    }

    public function confirmMockPaymentIntent(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $intent = $this->getPaymentIntent->find((string) $request->param('id'));
        if ($intent === null) {
            return JsonResponse::notFound('Payment intent not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $intent['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for payment intent.', $request->requestId());
        }
        if (!$this->accessPolicy->canMutate($user, $caseObject)) {
            throw new ForbiddenException('You cannot confirm this payment intent.');
        }

        return JsonResponse::ok($this->confirmMockPaymentIntent->handle((string) $intent['id']), $request->requestId());
    }
}
