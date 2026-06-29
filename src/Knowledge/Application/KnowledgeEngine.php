<?php

declare(strict_types=1);

namespace Reborn\Knowledge\Application;

use PDO;

final class KnowledgeEngine
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function bestMatch(string $text): array
    {
        $rules = [
            'oakley' => ['product' => 'Oakley Eye Jacket', 'component' => 'temple hinge / nose bridge', 'confidence_score' => 0.81],
            'garmin' => ['product' => 'Garmin wearable', 'component' => 'strap / charging port cover', 'confidence_score' => 0.78],
            'bambu' => ['product' => 'Bambu Lab printer accessory', 'component' => 'AMS or spool holder part', 'confidence_score' => 0.76],
            'lavatrice' => ['product' => 'Washing machine', 'component' => 'handle / knob / drawer', 'confidence_score' => 0.72],
            'washing' => ['product' => 'Washing machine', 'component' => 'handle / knob / drawer', 'confidence_score' => 0.72],
            'bike' => ['product' => 'Bicycle', 'component' => 'mount / clip / spacer', 'confidence_score' => 0.68],
        ];

        foreach ($rules as $needle => $match) {
            if (str_contains($text, $needle)) {
                return $match;
            }
        }

        $stmt = $this->pdo->query('SELECT label, metadata, confidence_score FROM knowledge_nodes ORDER BY confidence_score DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [];
        }

        $metadata = json_decode($row['metadata'] ?: '{}', true);

        return [
            'product' => $metadata['product'] ?? $row['label'],
            'component' => $metadata['component'] ?? 'repairable component',
            'confidence_score' => (float) $row['confidence_score'],
        ];
    }
}
