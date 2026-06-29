<?php

declare(strict_types=1);

namespace Reborn\Learning\Application;

use PDO;
use Reborn\Learning\Domain\RepairCompletionReport;
use Reborn\Learning\Domain\RepairLearningEvent;
use Reborn\Repair\Domain\RepairCase;
use Reborn\Shared\Support\Uuid;

final class KnowledgeGraphFeedbackService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function apply(RepairCompletionReport $report, RepairLearningEvent $learningEvent, ?RepairCase $repairCase): array
    {
        $now = gmdate('c');
        $labelBase = $repairCase ? $repairCase->title : 'Completed repair outcome';
        $label = $labelBase . ' · repair outcome';
        $confidenceScore = $report->outcomeStatus === 'successful' ? 0.86 : 0.58;
        if ($report->customerConfirmed) {
            $confidenceScore += 0.04;
        }
        $confidenceScore = min(0.95, $confidenceScore);

        $metadata = [
            'source' => 'repair_completion_report',
            'completion_report_id' => $report->id,
            'learning_event_id' => $learningEvent->id,
            'fulfilment_id' => $report->fulfilmentId,
            'repair_case_id' => $report->repairCaseId,
            'provider_id' => $report->providerId,
            'outcome_status' => $report->outcomeStatus,
            'functional_result' => $report->functionalResult,
            'object_saved' => $report->objectSaved,
            'co2_avoided_grams' => $report->co2AvoidedGrams,
            'category' => $repairCase?->category,
            'recognized_product' => $repairCase?->recognizedProduct,
            'recognized_component' => $repairCase?->recognizedComponent,
        ];

        $knowledgeNodeId = Uuid::v4();
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_nodes (id, type, label, confidence_score, metadata, created_at) VALUES (:id, :type, :label, :confidence_score, :metadata, :created_at)');
        $stmt->execute([
            'id' => $knowledgeNodeId,
            'type' => 'repair_outcome',
            'label' => $label,
            'confidence_score' => $confidenceScore,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        $caseNodeId = $this->findOrCreateRepairCaseNode($report, $repairCase, $now);
        $edgeId = Uuid::v4();
        $edgeMetadata = [
            'completion_report_id' => $report->id,
            'learning_event_id' => $learningEvent->id,
            'relation_source' => 'step16_learning_feedback',
        ];
        $edge = $this->pdo->prepare('INSERT INTO knowledge_edges (id, source_node_id, target_node_id, relation, confidence_score, metadata, created_at) VALUES (:id, :source_node_id, :target_node_id, :relation, :confidence_score, :metadata, :created_at)');
        $edge->execute([
            'id' => $edgeId,
            'source_node_id' => $caseNodeId,
            'target_node_id' => $knowledgeNodeId,
            'relation' => 'confirmed_by_repair_outcome',
            'confidence_score' => $confidenceScore,
            'metadata' => json_encode($edgeMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        return [
            'knowledge_node_id' => $knowledgeNodeId,
            'repair_case_node_id' => $caseNodeId,
            'knowledge_edge_id' => $edgeId,
            'confidence_score' => $confidenceScore,
            'applied_at' => $now,
        ];
    }

    private function findOrCreateRepairCaseNode(RepairCompletionReport $report, ?RepairCase $repairCase, string $now): string
    {
        $id = Uuid::v4();
        $metadata = [
            'repair_case_id' => $report->repairCaseId,
            'category' => $repairCase?->category,
            'status' => $repairCase?->status,
            'recognized_product' => $repairCase?->recognizedProduct,
            'recognized_component' => $repairCase?->recognizedComponent,
        ];
        $this->pdo->prepare('INSERT INTO knowledge_nodes (id, type, label, confidence_score, metadata, created_at) VALUES (:id, :type, :label, :confidence_score, :metadata, :created_at)')->execute([
            'id' => $id,
            'type' => 'repair_case',
            'label' => $repairCase ? $repairCase->title : 'Repair case ' . substr($report->repairCaseId, 0, 8),
            'confidence_score' => 0.72,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        return $id;
    }
}
