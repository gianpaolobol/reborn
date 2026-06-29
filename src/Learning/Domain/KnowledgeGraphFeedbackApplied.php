<?php

declare(strict_types=1);

namespace Reborn\Learning\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class KnowledgeGraphFeedbackApplied implements DomainEvent
{
    public function __construct(
        private readonly string $knowledgeNodeId,
        private readonly string $completionReportId,
        private readonly string $learningEventId,
        private readonly string $repairCaseId,
        private readonly float $confidenceScore,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'knowledge.graph_feedback_applied';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'knowledge_node_id' => $this->knowledgeNodeId,
            'completion_report_id' => $this->completionReportId,
            'learning_event_id' => $this->learningEventId,
            'repair_case_id' => $this->repairCaseId,
            'confidence_score' => $this->confidenceScore,
        ];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
