<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use Reborn\Marketplace\Domain\RepairPathDecision;
use Reborn\Marketplace\Domain\RepairPathDecisionRepository;
use Reborn\Provider\Domain\ProviderMatchCompleted;
use Reborn\Provider\Domain\ProviderMatchRepository;
use Reborn\Provider\Domain\ProviderMatchRequested;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class RequestProviderMatchService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RepairPathDecisionRepository $decisions,
        private readonly ProviderMatchRepository $providerMatches,
        private readonly ProviderMatchEngine $engine,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{provider_match: array<string, mixed>} */
    public function handle(string $repairCaseId, string $requestedBy, ?string $repairPathDecisionId = null): array
    {
        $case = $this->repairCases->find($repairCaseId);
        if ($case === null) {
            throw new NotFoundException('Repair case not found.');
        }

        $decision = $this->resolveDecision($repairCaseId, $repairPathDecisionId);

        $this->eventBus->publish(new ProviderMatchRequested($repairCaseId, $decision?->id, $requestedBy, gmdate('c')));
        $result = $this->engine->match($case, $decision);
        $match = $this->providerMatches->createCompleted($repairCaseId, $decision?->id, $requestedBy, $result);

        $providers = is_array($result['ranked_providers'] ?? null) ? $result['ranked_providers'] : [];
        $topProviderId = isset($providers[0]['provider_id']) ? (string) $providers[0]['provider_id'] : null;
        $this->eventBus->publish(new ProviderMatchCompleted($repairCaseId, $match->id, $topProviderId, count($providers), gmdate('c')));

        return ['provider_match' => $match->toArray()];
    }

    private function resolveDecision(string $repairCaseId, ?string $repairPathDecisionId): ?RepairPathDecision
    {
        if ($repairPathDecisionId !== null && trim($repairPathDecisionId) !== '') {
            $decision = $this->decisions->find(trim($repairPathDecisionId));
            if ($decision === null) {
                throw new NotFoundException('Repair path decision not found.');
            }
            if ($decision->repairCaseId !== $repairCaseId) {
                throw new ValidationException(['repair_path_decision_id' => ['Repair path decision must belong to this repair case.']]);
            }
            if ($decision->status !== 'completed') {
                throw new ValidationException(['repair_path_decision_id' => ['Repair path decision must be completed before matching providers.']]);
            }
            return $decision;
        }

        foreach ($this->decisions->listByRepairCase($repairCaseId) as $decision) {
            if ($decision->status === 'completed') {
                return $decision;
            }
        }

        return null;
    }
}
