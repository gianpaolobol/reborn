<?php

declare(strict_types=1);

namespace Reborn\Dashboard\Application;

use PDO;
use Reborn\Identity\Domain\User;

final class UserDashboardService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        return $this->forRole($user->role, $user);
    }

    /** @return array<string, mixed> */
    public function forRole(string $role, User $actor): array
    {
        return match ($role) {
            User::ROLE_REPAIR_USER => $this->repairUserDashboard($actor->role === User::ROLE_REPAIR_USER ? $actor : $this->demoUser(User::ROLE_REPAIR_USER) ?? $actor),
            User::ROLE_MAKER => $this->makerDashboard($actor->role === User::ROLE_MAKER ? $actor : $this->demoUser(User::ROLE_MAKER) ?? $actor),
            User::ROLE_PROVIDER => $this->providerDashboard($actor->role === User::ROLE_PROVIDER ? $actor : $this->demoUser(User::ROLE_PROVIDER) ?? $actor),
            User::ROLE_ENTERPRISE => $this->enterpriseDashboard($actor->role === User::ROLE_ENTERPRISE ? $actor : $this->demoUser(User::ROLE_ENTERPRISE) ?? $actor),
            User::ROLE_ADMIN => $this->adminDashboard($actor),
            default => $this->repairUserDashboard($actor),
        };
    }

    /** @return array<string, mixed> */
    private function repairUserDashboard(User $user): array
    {
        $cases = $this->rows('SELECT id, title, category, status, recognized_product, recognized_component, confidence_score, created_at, updated_at FROM repair_cases WHERE owner_id = :owner_id ORDER BY created_at DESC LIMIT 10', ['owner_id' => $user->id]);

        return [
            'role' => User::ROLE_REPAIR_USER,
            'owner_id' => $user->id,
            'headline' => 'Your repair workbench',
            'metrics' => [
                'total_cases' => $this->count('SELECT COUNT(*) FROM repair_cases WHERE owner_id = :owner_id', ['owner_id' => $user->id]),
                'open_cases' => $this->count("SELECT COUNT(*) FROM repair_cases WHERE owner_id = :owner_id AND status != 'completed'", ['owner_id' => $user->id]),
                'diagnosed_cases' => $this->count("SELECT COUNT(*) FROM repair_cases WHERE owner_id = :owner_id AND status = 'diagnosed'", ['owner_id' => $user->id]),
                'attachments' => $this->count('SELECT COUNT(*) FROM repair_attachments WHERE repair_case_id IN (SELECT id FROM repair_cases WHERE owner_id = :owner_id)', ['owner_id' => $user->id]),
            ],
            'repair_cases' => $cases,
            'next_actions' => $this->nextActionsForCases($cases),
        ];
    }

    /** @return array<string, mixed> */
    private function makerDashboard(User $user): array
    {
        $models = $this->rows('SELECT id, title, component_label, license, royalty_percent, verification_status, created_at FROM cad_models WHERE maker_id = :maker_id ORDER BY created_at DESC LIMIT 10', ['maker_id' => $user->id]);
        $opportunities = $this->rows("SELECT id, title, category, recognized_product, recognized_component, confidence_score FROM repair_cases WHERE status = 'diagnosed' ORDER BY confidence_score DESC LIMIT 10");

        return [
            'role' => User::ROLE_MAKER,
            'maker_id' => $user->id,
            'headline' => 'Maker royalty and model opportunities',
            'metrics' => [
                'models_uploaded' => count($models),
                'verified_models' => $this->count("SELECT COUNT(*) FROM cad_models WHERE maker_id = :maker_id AND verification_status = 'verified'", ['maker_id' => $user->id]),
                'royalty_percent_average' => $this->decimal('SELECT AVG(royalty_percent) FROM cad_models WHERE maker_id = :maker_id', ['maker_id' => $user->id]),
                'open_opportunities' => count($opportunities),
            ],
            'models' => $models,
            'opportunities' => $opportunities,
        ];
    }

    /** @return array<string, mixed> */
    private function providerDashboard(User $user): array
    {
        $openCases = $this->rows("SELECT id, title, category, recognized_product, recognized_component, confidence_score, updated_at FROM repair_cases WHERE status IN ('diagnosed', 'intake_received') ORDER BY updated_at DESC LIMIT 10");
        $providers = $this->rows('SELECT id, name, city, country, capabilities, rating, average_lead_time_days FROM providers ORDER BY rating DESC LIMIT 5');

        foreach ($providers as &$provider) {
            $provider['capabilities'] = json_decode((string) ($provider['capabilities'] ?? '[]'), true) ?: [];
            $provider['rating'] = (float) $provider['rating'];
            $provider['average_lead_time_days'] = (int) $provider['average_lead_time_days'];
        }
        unset($provider);

        return [
            'role' => User::ROLE_PROVIDER,
            'provider_user_id' => $user->id,
            'headline' => 'Provider fulfilment queue',
            'metrics' => [
                'open_repair_cases' => count($openCases),
                'provider_profiles' => count($providers),
                'average_network_rating' => $this->decimal('SELECT AVG(rating) FROM providers'),
                'average_lead_time_days' => $this->decimal('SELECT AVG(average_lead_time_days) FROM providers'),
            ],
            'candidate_jobs' => $openCases,
            'provider_network' => $providers,
        ];
    }

    /** @return array<string, mixed> */
    private function enterpriseDashboard(User $user): array
    {
        return [
            'role' => User::ROLE_ENTERPRISE,
            'enterprise_user_id' => $user->id,
            'headline' => 'Enterprise repair intelligence cockpit',
            'metrics' => [
                'repair_cases' => $this->count('SELECT COUNT(*) FROM repair_cases'),
                'objects_saved_estimate' => $this->count("SELECT COUNT(*) FROM repair_cases WHERE status IN ('diagnosed', 'completed')"),
                'knowledge_nodes' => $this->count('SELECT COUNT(*) FROM knowledge_nodes'),
                'provider_network' => $this->count('SELECT COUNT(*) FROM providers'),
            ],
            'category_breakdown' => $this->rows('SELECT category, COUNT(*) AS total FROM repair_cases GROUP BY category ORDER BY total DESC'),
            'latest_cases' => $this->rows('SELECT id, title, category, status, confidence_score, updated_at FROM repair_cases ORDER BY updated_at DESC LIMIT 10'),
        ];
    }

    /** @return array<string, mixed> */
    private function adminDashboard(User $user): array
    {
        return [
            'role' => User::ROLE_ADMIN,
            'admin_user_id' => $user->id,
            'headline' => 'Re-born operating system control room',
            'metrics' => [
                'users' => $this->count('SELECT COUNT(*) FROM users'),
                'repair_cases' => $this->count('SELECT COUNT(*) FROM repair_cases'),
                'domain_events' => $this->count('SELECT COUNT(*) FROM domain_events'),
                'attachments' => $this->count('SELECT COUNT(*) FROM repair_attachments'),
            ],
            'users_by_role' => $this->rows('SELECT role, COUNT(*) AS total FROM users GROUP BY role ORDER BY role ASC'),
            'cases_by_status' => $this->rows('SELECT status, COUNT(*) AS total FROM repair_cases GROUP BY status ORDER BY status ASC'),
            'latest_events' => array_map(function (array $row): array {
                $row['payload'] = json_decode((string) ($row['payload'] ?? '{}'), true) ?: [];
                return $row;
            }, $this->rows('SELECT id, name, payload, occurred_at FROM domain_events ORDER BY occurred_at DESC LIMIT 10')),
        ];
    }

    private function demoUser(string $role): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE role = :role ORDER BY created_at ASC LIMIT 1');
        $stmt->execute(['role' => $role]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? User::fromRow($row) : null;
    }

    /** @param array<string, mixed> $params */
    private function count(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $params */
    private function decimal(string $sql, array $params = []): float
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return round((float) ($value ?: 0), 2);
    }

    /** @param array<string, mixed> $params @return list<array<string, mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param list<array<string, mixed>> $cases @return list<array<string, string>> */
    private function nextActionsForCases(array $cases): array
    {
        $actions = [];
        foreach ($cases as $case) {
            $status = (string) ($case['status'] ?? 'unknown');
            $actions[] = match ($status) {
                'intake_received' => ['case_id' => (string) $case['id'], 'action' => 'Run diagnosis', 'reason' => 'The case has not yet entered the Repair Intelligence Engine.'],
                'diagnosed' => ['case_id' => (string) $case['id'], 'action' => 'Choose repair path', 'reason' => 'Recognition is available and repair options can be ranked.'],
                default => ['case_id' => (string) $case['id'], 'action' => 'Review status', 'reason' => 'The case needs user confirmation or operational follow-up.'],
            };
        }

        return $actions;
    }
}
