<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use PDO;
use Reborn\AI\Domain\RecognitionJob;
use Reborn\AI\Domain\RecognitionJobRepository;
use Reborn\Marketplace\Domain\RepairPathDecisionCompleted;
use Reborn\Marketplace\Domain\RepairPathDecisionRepository;
use Reborn\Marketplace\Domain\RepairPathDecisionRequested;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class RequestRepairPathDecisionService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RecognitionJobRepository $recognitionJobs,
        private readonly RepairPathDecisionRepository $decisions,
        private readonly RepairPathDecisionEngine $engine,
        private readonly EventBus $eventBus,
        private readonly PDO $pdo,
    ) {
    }

    /** @return array{decision: array<string, mixed>, repair_paths: list<array<string, mixed>>} */
    public function handle(string $repairCaseId, string $requestedBy, ?string $recognitionJobId = null): array
    {
        $case = $this->repairCases->find($repairCaseId);
        if ($case === null) {
            throw new NotFoundException('Repair case not found.');
        }

        $recognitionJob = $this->resolveRecognitionJob($repairCaseId, $recognitionJobId);

        $this->eventBus->publish(new RepairPathDecisionRequested($repairCaseId, $recognitionJob?->id, $requestedBy, gmdate('c')));

        $result = $this->engine->decide($case, $recognitionJob);
        $paths = $this->persistRepairPaths($repairCaseId, $result['ranked_paths']);

        $decisionResult = $result + [
            'repair_paths_persisted' => count($paths),
            'repair_path_ids' => array_map(static fn(array $path): string => (string) $path['id'], $paths),
        ];

        $decision = $this->decisions->createCompleted($repairCaseId, $recognitionJob?->id, $requestedBy, $decisionResult);
        $this->eventBus->publish(new RepairPathDecisionCompleted(
            $repairCaseId,
            $decision->id,
            (string) $decisionResult['recommended_path'],
            (float) ($decisionResult['ranked_paths'][0]['score'] ?? 0),
            gmdate('c')
        ));

        return [
            'decision' => $decision->toArray(),
            'repair_paths' => $paths,
        ];
    }

    private function resolveRecognitionJob(string $repairCaseId, ?string $recognitionJobId): ?RecognitionJob
    {
        if ($recognitionJobId !== null && trim($recognitionJobId) !== '') {
            $job = $this->recognitionJobs->find(trim($recognitionJobId));
            if ($job === null) {
                throw new NotFoundException('Recognition job not found.');
            }
            if ($job->repairCaseId !== $repairCaseId) {
                throw new ValidationException(['recognition_job_id' => ['Recognition job must belong to this repair case.']]);
            }
            if ($job->status !== 'completed') {
                throw new ValidationException(['recognition_job_id' => ['Recognition job must be completed before decision.']]);
            }
            return $job;
        }

        foreach ($this->recognitionJobs->listByRepairCase($repairCaseId) as $job) {
            if ($job->status === 'completed') {
                return $job;
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $rankedPaths @return list<array<string, mixed>> */
    private function persistRepairPaths(string $repairCaseId, array $rankedPaths): array
    {
        $this->pdo->prepare('DELETE FROM repair_paths WHERE repair_case_id = :repair_case_id')->execute(['repair_case_id' => $repairCaseId]);
        $stmt = $this->pdo->prepare('INSERT INTO repair_paths (id, repair_case_id, type, title, description, confidence_score, estimated_price_cents, estimated_days, created_at) VALUES (:id, :repair_case_id, :type, :title, :description, :confidence_score, :estimated_price_cents, :estimated_days, :created_at)');

        $persisted = [];
        $now = gmdate('c');
        foreach ($rankedPaths as $path) {
            $row = [
                'id' => Uuid::v4(),
                'repair_case_id' => $repairCaseId,
                'type' => (string) $path['type'],
                'title' => (string) $path['title'],
                'description' => (string) $path['description'],
                'confidence_score' => (float) $path['score'],
                'estimated_price_cents' => (int) $path['estimated_price_cents'],
                'estimated_days' => (int) $path['estimated_days'],
                'created_at' => $now,
            ];
            $stmt->execute($row);
            $persisted[] = $row + [
                'next_actions' => $path['next_actions'] ?? [],
                'risk_flags' => $path['risk_flags'] ?? [],
            ];
        }

        return $persisted;
    }
}
