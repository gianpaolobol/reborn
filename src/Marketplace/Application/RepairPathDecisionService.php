<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use PDO;
use Reborn\Repair\Domain\RepairCase;
use Reborn\Shared\Support\Uuid;

final class RepairPathDecisionService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $diagnosis @return list<array<string, mixed>> */
    public function generatePaths(RepairCase $case, array $diagnosis): array
    {
        $paths = [
            [
                'id' => Uuid::v4(),
                'repair_case_id' => $case->id,
                'type' => 'existing_part',
                'title' => 'Recover an existing verified part',
                'description' => 'Search the knowledge graph and marketplace for a compatible replacement part.',
                'confidence_score' => min(0.95, ($diagnosis['confidence_score'] ?? 0.5) + 0.08),
                'estimated_price_cents' => 1490,
                'estimated_days' => 3,
            ],
            [
                'id' => Uuid::v4(),
                'repair_case_id' => $case->id,
                'type' => 'ai_generated_cad',
                'title' => 'Generate a CAD replacement through AI fallback',
                'description' => 'Use AI-assisted reconstruction when no verified CAD model exists.',
                'confidence_score' => 0.64,
                'estimated_price_cents' => 2490,
                'estimated_days' => 5,
            ],
            [
                'id' => Uuid::v4(),
                'repair_case_id' => $case->id,
                'type' => 'provider_assisted_repair',
                'title' => 'Route to a local repair provider',
                'description' => 'Assign the case to a nearby provider for validation, printing and delivery.',
                'confidence_score' => 0.71,
                'estimated_price_cents' => 2990,
                'estimated_days' => 4,
            ],
        ];

        $this->pdo->prepare('DELETE FROM repair_paths WHERE repair_case_id = :repair_case_id')->execute(['repair_case_id' => $case->id]);
        $stmt = $this->pdo->prepare('INSERT INTO repair_paths (id, repair_case_id, type, title, description, confidence_score, estimated_price_cents, estimated_days, created_at) VALUES (:id, :repair_case_id, :type, :title, :description, :confidence_score, :estimated_price_cents, :estimated_days, :created_at)');

        foreach ($paths as $path) {
            $stmt->execute($path + ['created_at' => gmdate('c')]);
        }

        return $paths;
    }
}
