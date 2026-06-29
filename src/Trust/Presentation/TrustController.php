<?php

declare(strict_types=1);

namespace Reborn\Trust\Presentation;

use Reborn\Identity\Application\AuthContext;
use Reborn\Identity\Domain\User;
use Reborn\Learning\Application\GetCompletionReportService;
use Reborn\Repair\Application\GetRepairCaseService;
use Reborn\Repair\Application\RepairCaseAccessPolicy;
use Reborn\Shared\Http\ForbiddenException;
use Reborn\Shared\Http\JsonResponse;
use Reborn\Shared\Http\Request;
use Reborn\Trust\Application\CreateTrustReviewService;
use Reborn\Trust\Application\GetProviderQualityScoreService;
use Reborn\Trust\Application\ListProviderQualityScoresService;
use Reborn\Trust\Application\ListProviderTrustSignalsService;
use Reborn\Trust\Application\ListTrustReviewsService;

final class TrustController
{
    public function __construct(
        private readonly CreateTrustReviewService $createTrustReview,
        private readonly ListTrustReviewsService $listTrustReviews,
        private readonly GetProviderQualityScoreService $getProviderQualityScore,
        private readonly ListProviderQualityScoresService $listProviderQualityScores,
        private readonly ListProviderTrustSignalsService $listProviderTrustSignals,
        private readonly GetCompletionReportService $getCompletionReport,
        private readonly GetRepairCaseService $getRepairCase,
        private readonly AuthContext $auth,
        private readonly RepairCaseAccessPolicy $accessPolicy,
    ) {
    }

    public function storeReviewForCompletionReport(Request $request): JsonResponse
    {
        $user = $this->auth->user($request);
        $this->requireReviewer($user);
        $report = $this->getCompletionReport->find((string) $request->param('id'));
        if ($report === null) {
            return JsonResponse::notFound('Repair completion report not found.', $request->requestId());
        }
        $caseObject = $this->getRepairCase->find((string) $report['repair_case_id']);
        if ($caseObject === null) {
            return JsonResponse::notFound('Repair case not found for completion report.', $request->requestId());
        }
        if (!$this->accessPolicy->canView($user, $caseObject)) {
            throw new ForbiddenException('You cannot review trust for this completion report.');
        }

        return JsonResponse::created($this->createTrustReview->handle((string) $report['id'], $user, $request->body()), $request->requestId());
    }

    public function reviewsForCompletionReport(Request $request): JsonResponse
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
            throw new ForbiddenException('You cannot view trust reviews for this completion report.');
        }

        return JsonResponse::ok(['trust_reviews' => $this->listTrustReviews->forCompletionReport((string) $report['id'])], $request->requestId());
    }

    public function providerQualityScore(Request $request): JsonResponse
    {
        $this->auth->user($request);
        $providerId = (string) $request->param('id');
        $score = $this->getProviderQualityScore->find($providerId);
        if ($score === null) {
            return JsonResponse::ok([
                'quality_score' => [
                    'provider_id' => $providerId,
                    'review_count' => 0,
                    'completed_repairs_count' => 0,
                    'successful_repairs_count' => 0,
                    'average_rating' => 0,
                    'quality_score' => 0,
                    'reliability_score' => 0,
                    'communication_score' => 0,
                    'timeliness_score' => 0,
                    'overall_score' => 0,
                    'trust_tier' => 'unrated',
                    'last_review_id' => null,
                    'score_json' => ['summary' => 'No trust signal recorded yet.'],
                    'updated_at' => null,
                ],
            ], $request->requestId());
        }

        return JsonResponse::ok(['quality_score' => $score], $request->requestId());
    }

    public function providerQualityScores(Request $request): JsonResponse
    {
        $this->auth->user($request);
        return JsonResponse::ok(['quality_scores' => $this->listProviderQualityScores->handle()], $request->requestId());
    }

    public function providerTrustSignals(Request $request): JsonResponse
    {
        $this->auth->user($request);
        $providerId = (string) $request->param('id');
        return JsonResponse::ok(['trust_signals' => $this->listProviderTrustSignals->handle($providerId)], $request->requestId());
    }

    public function providerTrustReviews(Request $request): JsonResponse
    {
        $this->auth->user($request);
        $providerId = (string) $request->param('id');
        return JsonResponse::ok(['trust_reviews' => $this->listTrustReviews->forProvider($providerId)], $request->requestId());
    }

    private function requireReviewer(User $user): void
    {
        if (!$user->hasAnyRole([User::ROLE_REPAIR_USER, User::ROLE_ENTERPRISE, User::ROLE_ADMIN])) {
            throw new ForbiddenException('Only repair users, enterprise users or admins can submit provider trust reviews.');
        }
    }
}
