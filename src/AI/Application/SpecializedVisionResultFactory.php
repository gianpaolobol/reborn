<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

final class SpecializedVisionResultFactory
{
    /**
     * @param list<array<string, mixed>> $predictions
     * @param list<mixed> $attachments
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function resultFromPredictions(array $predictions, array $attachments, array $meta): array
    {
        $normalised = [];
        foreach ($predictions as $prediction) {
            $label = trim((string) ($prediction['label'] ?? $prediction['name'] ?? $prediction['class'] ?? ''));
            if ($label === '') {
                continue;
            }

            $confidence = self::normaliseConfidence($prediction['confidence'] ?? $prediction['value'] ?? 0.0);
            $normalised[] = [
                'label' => $label,
                'human_label' => self::humanLabel($label),
                'confidence' => $confidence,
                'source' => (string) ($prediction['source'] ?? ($meta['provider'] ?? 'specialized_cv')),
                'raw' => $prediction['raw'] ?? $prediction,
            ];
        }

        usort($normalised, static fn(array $a, array $b): int => ((float) $b['confidence']) <=> ((float) $a['confidence']));

        $minConfidence = self::normaliseConfidence($meta['min_confidence'] ?? 0.5);
        $top = $normalised[0] ?? null;
        $topLabel = is_array($top) ? (string) $top['label'] : '';
        $topHuman = is_array($top) ? (string) $top['human_label'] : 'componente specializzato da confermare';
        $topConfidence = is_array($top) ? (float) $top['confidence'] : 0.0;
        $recognized = $top !== null && $topConfidence >= $minConfidence;
        $joinedLabels = implode(' ', array_map(static fn(array $item): string => (string) $item['label'], $normalised));
        $partNumber = self::extractPartNumber($joinedLabels);
        $visibleText = array_values(array_unique(array_map(static fn(array $item): string => (string) $item['human_label'], array_slice($normalised, 0, 6))));

        $provider = (string) ($meta['provider'] ?? 'specialized_cv');
        $model = (string) ($meta['model'] ?? 'custom_model');
        $mode = (string) ($meta['recognition_mode'] ?? ($provider . '_custom_vision_api'));
        $providerStatus = (string) ($meta['provider_status'] ?? 'live_response');
        $why = $recognized
            ? 'Modello custom/specializzato ha trovato una classe compatibile con confidenza sufficiente.'
            : 'Modello custom/specializzato non ha superato la soglia minima di confidenza.';

        return [
            'identification' => [
                'status' => $recognized ? 'recognized' : 'unclear',
                'source_image_type' => 'user_uploaded_photo_or_reference_image',
                'visible_text' => $visibleText,
                'part_number' => $partNumber,
                'commercial_name' => $recognized ? $topHuman : '',
                'possible_brands' => self::extractConfiguredList($meta, 'possible_brands'),
                'possible_models' => [],
                'external_lookup_summary' => '',
                'why' => $why,
            ],
            'part_spec' => [
                'name_it' => $recognized ? $topHuman : 'componente specializzato da confermare',
                'name_en' => $recognized ? $topHuman : 'specialized component to confirm',
                'appliance_context' => (string) ($meta['appliance_context'] ?? 'Da confermare.'),
                'known_dimensions' => [],
                'key_features' => array_map(static fn(array $item): string => ((string) $item['human_label']) . ' (' . round(((float) $item['confidence']) * 100, 1) . '%)', array_slice($normalised, 0, 5)),
                'compatibility_clues' => $partNumber !== '' ? ['classe/codice custom: ' . $partNumber] : [],
                'manufacturing_features' => ['Risultato da modello custom: verificare sempre geometria, materiale e misure prima della produzione.'],
            ],
            'object_guess' => [
                'label' => $recognized ? $topHuman : 'componente specializzato da confermare',
                'confidence' => $topConfidence,
                'object_context' => 'Riconoscimento da piattaforma specializzata/addestrabile di computer vision.',
            ],
            'damage_assessment' => [
                'type' => 'da verificare',
                'severity' => 'review',
                'repairability_score' => $recognized ? 0.72 : 0.45,
            ],
            'replacement_part_brief' => [
                'plain_language_summary' => $recognized
                    ? 'Il modello specializzato ha riconosciuto: ' . $topHuman . '.'
                    : 'Il modello specializzato non ha riconosciuto il pezzo con sufficiente sicurezza.',
                'probable_function' => $recognized ? 'Funzione da collegare alla classe custom riconosciuta: ' . $topHuman . '.' : 'Funzione da confermare.',
                'part_family' => $recognized ? $topHuman : 'componente custom da confermare',
                'manufacturing_candidate' => true,
                'material_hint' => 'Da scegliere dopo verifica: materiale originale, ambiente d’uso, temperatura, carichi e usura.',
                'critical_dimensions' => ['larghezza totale', 'altezza totale', 'spessore', 'diametri/fori/clip se presenti'],
                'photo_requirements' => ['foto frontale nitida', 'foto laterale', 'foto con righello o calibro', 'foto del punto di montaggio'],
                'user_questions' => ['La classe riconosciuta corrisponde al pezzo reale?', 'Hai foto del pezzo montato?', 'Puoi indicare marca, modello o codice visibile?'],
            ],
            'recommended_next_step' => [
                'path' => $recognized ? 'find_existing_spare' : 'ask_more_photos',
                'reason' => $recognized ? 'La classe custom riconosciuta permette di cercare prima un modello/ricambio esistente.' : 'Servono più immagini o training migliore del modello custom.',
            ],
            'suggested_inputs' => ['misure principali', 'foto laterale', 'foto con righello o calibro', 'conferma manuale della classe custom'],
            'repair_notes' => ['Risultato da provider specializzato. Serve validazione umana prima di stampa, vendita o download.'],
            'custom_vision' => [
                'provider' => $provider,
                'model' => $model,
                'min_confidence' => $minConfidence,
                'predictions' => $normalised,
                'training_note' => (string) ($meta['training_note'] ?? 'Addestra/aggiorna il modello nella piattaforma del provider con esempi positivi/negativi del pezzo specifico.'),
            ],
            'recognition_mode' => $mode,
            'ai_provider' => [
                'provider' => $provider,
                'status' => $providerStatus,
                'model' => $model,
                'image_count' => max(1, count($attachments)),
                'prompt_profile' => 'specialized_trainable_computer_vision_v1',
            ],
        ];
    }

    private static function normaliseConfidence(mixed $value): float
    {
        $confidence = is_numeric($value) ? (float) $value : 0.0;
        if ($confidence > 1.0) {
            $confidence = $confidence / 100.0;
        }

        return max(0.0, min(1.0, $confidence));
    }

    private static function humanLabel(string $label): string
    {
        $label = trim($label);
        $label = preg_replace('/[_-]+/', ' ', $label) ?? $label;
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        return trim($label);
    }

    private static function extractPartNumber(string $text): string
    {
        if (preg_match('/\b([A-Z]{1,6}[-_ ]?[A-Z0-9]{2,}[-_ ]?[A-Z0-9]{1,})\b/i', $text, $matches)) {
            return strtoupper(str_replace([' ', '_'], '-', trim($matches[1])));
        }

        return '';
    }

    /** @return list<string> */
    private static function extractConfiguredList(array $meta, string $key): array
    {
        $value = $meta[$key] ?? [];
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn(mixed $item): string => trim((string) $item), $value), static fn(string $item): bool => $item !== ''));
    }
}
