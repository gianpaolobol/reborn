<?php

declare(strict_types=1);

namespace Reborn\Governance\Application;

use Reborn\Governance\Domain\MarketplaceGovernanceRepository;
use Reborn\Governance\Domain\ProviderGovernanceActionRecorded;
use Reborn\Identity\Domain\User;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\ValidationException;

final class RecordProviderGovernanceActionService
{
    public function __construct(
        private readonly MarketplaceGovernanceRepository $governanceRepository,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function handle(string $providerId, User $actor, array $payload): array
    {
        if ($providerId === '') {
            throw new ValidationException(['provider_id' => ['Provider id is required.']]);
        }
        $this->validate($payload);
        $action = $this->governanceRepository->recordProviderAction($providerId, $actor, $payload);
        $this->governanceRepository->audit($actor, 'provider_governance_action_recorded', 'provider', $providerId, $action->toArray());
        $this->eventBus->publish(new ProviderGovernanceActionRecorded($action->id, $action->providerId, $action->actionType, $action->severity, $action->scoreAdjustment, $actor->id, gmdate('c')));

        return ['governance_action' => $action->toArray()];
    }

    /** @param array<string, mixed> $payload */
    private function validate(array $payload): void
    {
        $errors = [];
        if (isset($payload['action_type']) && !in_array((string) $payload['action_type'], ['watchlist', 'suppress', 'manual_boost', 'manual_penalty', 'quality_review', 'policy_note'], true)) {
            $errors['action_type'] = ['Unsupported governance action type.'];
        }
        if (isset($payload['severity']) && !in_array((string) $payload['severity'], ['low', 'medium', 'high', 'critical'], true)) {
            $errors['severity'] = ['Severity must be low, medium, high or critical.'];
        }
        if (isset($payload['score_adjustment'])) {
            $adjustment = (float) $payload['score_adjustment'];
            if ($adjustment < -100 || $adjustment > 100) {
                $errors['score_adjustment'] = ['Score adjustment must be between -100 and 100.'];
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
