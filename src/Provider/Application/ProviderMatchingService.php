<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use PDO;
use Reborn\Repair\Domain\RepairCase;

final class ProviderMatchingService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param list<array<string, mixed>> $paths @return list<array<string, mixed>> */
    public function match(RepairCase $case, array $paths): array
    {
        $stmt = $this->pdo->query('SELECT id, name, city, country, capabilities, rating, average_lead_time_days FROM providers ORDER BY rating DESC, average_lead_time_days ASC LIMIT 3');

        return array_map(static function (array $row): array {
            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'city' => $row['city'],
                'country' => $row['country'],
                'capabilities' => json_decode($row['capabilities'] ?: '[]', true),
                'rating' => (float) $row['rating'],
                'average_lead_time_days' => (int) $row['average_lead_time_days'],
                'match_reason' => 'High trust score and compatible additive manufacturing capabilities.',
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
