<?php

declare(strict_types=1);

namespace Reborn\Provider\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Provider\Application\GetProviderMatchService;
use Reborn\Provider\Application\GetProviderQuoteRequestService;
use Reborn\Provider\Application\ListProviderMatchesService;
use Reborn\Provider\Application\ListProviderQuoteRequestsService;
use Reborn\Provider\Application\RequestProviderMatchService;
use Reborn\Provider\Application\RequestProviderQuoteService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Shared\Http\ValidationException;

final class ProviderMatchController
{
    public function __construct(
        private readonly RequestProviderMatchService $requestProviderMatch,
        private readonly ListProviderMatchesService $listProviderMatches,
        private readonly GetProviderMatchService $getProviderMatch,
        private readonly RequestProviderQuoteService $requestQuote,
        private readonly ListProviderQuoteRequestsService $listQuotes,
        private readonly GetProviderQuoteRequestService $getQuote,
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
            throw new ForbiddenException('You cannot view provider matches for this repair case.');
        }

        return JsonResponse::ok([
            'provider_matches' => $this->listProviderMatches->handle($repairCaseId),
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
            throw new ForbiddenException('You cannot request provider matching for this repair case.');
        }

        $body = $request->body();
        $decisionId = isset($body['repair_path_decision_id']) && trim((string) $body['repair_path_decision_id']) !== ''
            ? trim((string) $body['repair_path_decision_id'])
            : null;

        return JsonResponse::created($this->requestProviderMatch->handle($repairCaseId, $user->id, $decisionId), $request->requestId());
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $match = $this->getProviderMatch->find((string) $request->param('id'));
        if ($match === null) {
            return JsonResponse::notFound('Provider match not found.', $request->requestId());
        }

        $caseObject = $this->getRepairCase->find((string) $match['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for provider match.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this provider match.');
        }

        return JsonResponse::ok(['provider_match' => $match], $request->requestId());
    }

    public function storeQuote(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $match = $this->getProviderMatch->find((string) $request->param('id'));
        if ($match === null) {
            return JsonResponse::notFound('Provider match not found.', $request->requestId());
        }

        $caseObject = $this->getRepairCase->find((string) $match['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for provider match.', $request->requestId());
        }

        if (!$this->accessPolicy->canMutate($user, $caseObject)) {
            throw new ForbiddenException('You cannot request a quote for this repair case.');
        }

        $providerId = trim((string) $request->input('provider_id', ''));
        if ($providerId === '') {
            throw new ValidationException(['provider_id' => ['provider_id is required.']]);
        }

        return JsonResponse::created($this->requestQuote->handle((string) $match['id'], $providerId, $user->id), $request->requestId());
    }

    public function quotes(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $repairCaseId = (string) $request->param('id');
        $caseObject = $this->getRepairCase->find($repairCaseId);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view quote requests for this repair case.');
        }

        return JsonResponse::ok([
            'quote_requests' => $this->listQuotes->handle($repairCaseId),
        ], $request->requestId());
    }

    public function showQuote(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $quote = $this->getQuote->find((string) $request->param('id'));
        if ($quote === null) {
            return JsonResponse::notFound('Quote request not found.', $request->requestId());
        }

        $caseObject = $this->getRepairCase->find((string) $quote['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for quote request.', $request->requestId());
        }

        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot view this quote request.');
        }

        return JsonResponse::ok(['quote_request' => $quote], $request->requestId());
    }
}
