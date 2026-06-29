<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Knowledge\Application\KnowledgeEngine;

final class RecognitionEngine
{
    public function __construct(private readonly KnowledgeEngine $knowledgeEngine)
    {
    }

    /** @param array<string, mixed> $signals @return array<string, mixed> */
    public function recognize(array $signals): array
    {
        $text = strtolower(($signals['title'] ?? '') . ' ' . ($signals['description'] ?? '') . ' ' . ($signals['category'] ?? ''));
        $match = $this->knowledgeEngine->bestMatch($text);

        return [
            'recognized_product' => $match['product'] ?? 'Unknown product',
            'recognized_component' => $match['component'] ?? 'Unknown component',
            'confidence_score' => $match['confidence_score'] ?? 0.42,
            'recognition_mode' => 'mock_ai_plus_knowledge_graph',
            'signals' => [
                'textual_intake' => true,
                'image_pipeline' => 'planned',
                'dimensions_pipeline' => 'planned',
            ],
        ];
    }
}
