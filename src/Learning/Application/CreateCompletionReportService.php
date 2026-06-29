<?php

declare(strict_types=1);

namespace Reborn\Learning\Application;

use Reborn\Fulfilment\Domain\RepairFulfilmentRepository;
use Reborn\Learning\Domain\KnowledgeGraphFeedbackApplied;
use Reborn\Learning\Domain\LearningEventRecorded;
use Reborn\Learning\Domain\RepairCompletionReported;
use Reborn\Learning\Domain\RepairCompletionReportRepository;
use Reborn\Learning\Domain\RepairLearningEventRepository;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class CreateCompletionReportService
{
    private const ALLOWED_OUTCOMES = ['successful', 'partially_successful', 'failed'];
    private const ALLOWED_RESULTS = ['object_returned_to_function', 'temporary_fix', 'not_repairable', 'requires_follow_up'];

    public function __construct(
        private readonly RepairFulfilmentRepository $fulfilments,
        private readonly RepairCompletionReportRepository $reports,
        private readonly RepairLearningEventRepository $learningEvents,
        private readonly RepairCaseRepository $repairCases,
        private readonly KnowledgeGraphFeedbackService $knowledgeFeedback,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function handle(string $fulfilmentId, string $reportedBy, array $payload): array
    {
        $fulfilment = $this->fulfilments->find($fulfilmentId);
        if ($fulfilment === null) {
            throw new NotFoundException('Repair fulfilment not found.');
        }
        if ($fulfilment->status !== 'completed') {
            throw new ValidationException(['fulfilment' => ['Completion reports can be created only after fulfilment status is completed.']]);
        }

        $outcomeStatus = (string) ($payload['outcome_status'] ?? 'successful');
        if (!in_array($outcomeStatus, self::ALLOWED_OUTCOMES, true)) {
            throw new ValidationException(['outcome_status' => ['Invalid outcome status.']]);
        }
        $functionalResult = (string) ($payload['functional_result'] ?? 'object_returned_to_function');
        if (!in_array($functionalResult, self::ALLOWED_RESULTS, true)) {
            throw new ValidationException(['functional_result' => ['Invalid functional result.']]);
        }

        $repairCase = $this->repairCases->find($fulfilment->repairCaseId);
        $report = $this->reports->createFromFulfilment($fulfilment, $reportedBy, $payload + [
            'outcome_status' => $outcomeStatus,
            'functional_result' => $functionalResult,
        ]);

        $signal = $this->buildLearningSignal($report, $repairCase?->toArray());
        $confidenceDelta = $outcomeStatus === 'successful' ? 0.08 : ($outcomeStatus === 'partially_successful' ? 0.03 : -0.04);
        $learningEvent = $this->learningEvents->record($report, 'repair_outcome_confirmed', $signal, $confidenceDelta);
        $feedback = $this->knowledgeFeedback->apply($report, $learningEvent, $repairCase);

        $now = gmdate('c');
        $this->eventBus->publish(new RepairCompletionReported($report->id, $report->fulfilmentId, $report->repairOrderId, $report->repairCaseId, $report->providerId, $report->outcomeStatus, $reportedBy, $now));
        $this->eventBus->publish(new LearningEventRecorded($learningEvent->id, $report->id, $report->repairCaseId, $learningEvent->eventType, $learningEvent->confidenceDelta, $now));
        $this->eventBus->publish(new KnowledgeGraphFeedbackApplied((string) $feedback['knowledge_node_id'], $report->id, $learningEvent->id, $report->repairCaseId, (float) $feedback['confidence_score'], $now));

        return [
            'completion_report' => $report->toArray(),
            'learning_event' => $learningEvent->toArray(),
            'knowledge_feedback' => $feedback,
        ];
    }

    /** @param array<string, mixed>|null $repairCase @return array<string, mixed> */
    private function buildLearningSignal($report, ?array $repairCase): array
    {
        return [
            'source' => 'repair_completion_report',
            'completion_report_id' => $report->id,
            'fulfilment_id' => $report->fulfilmentId,
            'repair_case_id' => $report->repairCaseId,
            'provider_id' => $report->providerId,
            'category' => $repairCase['category'] ?? null,
            'recognized_product' => $repairCase['recognized_product'] ?? null,
            'recognized_component' => $repairCase['recognized_component'] ?? null,
            'outcome_status' => $report->outcomeStatus,
            'functional_result' => $report->functionalResult,
            'object_saved' => $report->objectSaved,
            'customer_confirmed' => $report->customerConfirmed,
            'co2_avoided_grams' => $report->co2AvoidedGrams,
            'repair_method' => $report->outcome['repair_method'] ?? null,
            'quality_checks' => $report->outcome['quality_checks'] ?? [],
        ];
    }
}
