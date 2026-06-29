<?php

declare(strict_types=1);

namespace Reborn\Repair\Application;

use Reborn\AI\Application\RecognitionEngine;
use Reborn\Marketplace\Application\RepairPathDecisionService;
use Reborn\Provider\Application\ProviderMatchingService;
use Reborn\Repair\Domain\RepairCaseDiagnosed;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Domain\EventBus;

final class DiagnoseRepairCaseService
{
    public function __construct(
        private readonly RepairCaseRepository $repository,
        private readonly RecognitionEngine $recognitionEngine,
        private readonly RepairPathDecisionService $decisionService,
        private readonly ProviderMatchingService $providerMatchingService,
        private readonly EventBus $eventBus,
    ) {
    }

    public function handle(string $id): ?array
    {
        $case = $this->repository->find($id);
        if ($case === null) {
            return null;
        }

        $diagnosis = $this->recognitionEngine->recognize([
            'title' => $case->title,
            'description' => $case->description,
            'category' => $case->category,
        ]);

        $updated = $this->repository->updateDiagnosis($id, $diagnosis);
        $paths = $this->decisionService->generatePaths($updated, $diagnosis);
        $providers = $this->providerMatchingService->match($updated, $paths);

        $this->eventBus->publish(new RepairCaseDiagnosed($updated->id, $updated->confidenceScore, gmdate('c')));

        return [
            'repair_case' => $updated->toArray(),
            'diagnosis' => $diagnosis,
            'repair_paths' => $paths,
            'providers' => $providers,
        ];
    }
}
