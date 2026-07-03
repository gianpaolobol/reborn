<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\AI\Domain\AIRecognitionCompleted;
use Reborn\AI\Domain\AIRecognitionRequested;
use Reborn\AI\Domain\RecognitionJobRepository;
use Reborn\Repair\Domain\RepairAttachment;
use Reborn\Repair\Domain\RepairAttachmentRepository;
use Reborn\Repair\Domain\RepairCase;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Marketplace\Application\SearchReplacementModelService;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class RequestRecognitionJobService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RepairAttachmentRepository $attachments,
        private readonly RecognitionJobRepository $recognitionJobs,
        private readonly EventBus $eventBus,
        private readonly PhotoRecognitionGateway $photoRecognitionGateway,
        private readonly ?SearchReplacementModelService $marketplaceModelSearch = null,
        private readonly ?ModelLibraryMatchService $modelLibraryMatches = null,
    ) {
    }

    /** @param list<string> $attachmentIds @return array<string, mixed> */
    public function handle(string $repairCaseId, string $requestedBy, array $attachmentIds, bool $deterministicSmoke = false): array
    {
        $case = $this->repairCases->find($repairCaseId);
        if ($case === null) {
            throw new NotFoundException('Repair case not found.');
        }

        $attachmentIds = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $attachmentIds
        ))));

        if ($attachmentIds === []) {
            throw new ValidationException(['attachment_ids' => ['At least one attachment id is required.']]);
        }

        if (count($attachmentIds) > 12) {
            throw new ValidationException(['attachment_ids' => ['No more than 12 attachments can be used in the MVP recognition request.']]);
        }

        $caseAttachments = $this->attachments->listByRepairCase($repairCaseId);
        $byId = [];
        foreach ($caseAttachments as $attachment) {
            $byId[$attachment->id] = $attachment;
        }

        $selectedAttachments = [];
        foreach ($attachmentIds as $attachmentId) {
            if (!isset($byId[$attachmentId])) {
                throw new ValidationException(['attachment_ids' => ['All attachment ids must belong to the repair case.']]);
            }
            $selectedAttachments[] = $byId[$attachmentId];
        }

        $job = $this->recognitionJobs->create($repairCaseId, $requestedBy, $attachmentIds);
        $this->eventBus->publish(new AIRecognitionRequested($repairCaseId, $job->id, $requestedBy, $attachmentIds, gmdate('c')));

        try {
            $this->recognitionJobs->markProcessing($job->id);

            // Minimal Live UI: the normal browser flow must call the configured live
            // provider through the PHP backend. Do not replace a provider miss with a
            // mock or cached demo recognition that could look real to the user.
            if ($deterministicSmoke) {
                $result = $this->deterministicSmokeResult($case, $selectedAttachments);
            } else {
                $sha256s = array_values(array_unique(array_filter(array_map(
                    static fn (RepairAttachment $attachment): string => trim($attachment->sha256),
                    $selectedAttachments
                ))));

                $result = null;
                if ($this->modelLibraryMatches !== null) {
                    $result = $this->modelLibraryMatches->buildRecognitionResultFromImageHashes($sha256s);
                }

                if ($result === null) {
                    $reusedJob = $this->recognitionJobs->findReusableLiveResultByAttachmentSha256($sha256s);
                    if ($reusedJob !== null && is_array($reusedJob->result)) {
                        $result = $this->reusedResult($reusedJob->result);
                        if ($this->modelLibraryMatches !== null) {
                            $matches = $this->modelLibraryMatches->findMatchesByImageHashes($sha256s, 10);
                            if ($matches !== []) {
                                $result['library_matches'] = array_map(static function (array $match): array {
                                    unset($match['_record']);
                                    return $match;
                                }, $matches);
                            }
                        }
                    }
                }

                if ($result === null) {
                    $result = $this->photoRecognitionGateway->analyze($case, $selectedAttachments);
                    if ($result === null) {
                        $result = $this->providerUnavailableResult($case, $selectedAttachments, 'No enabled and configured live provider returned a result.');
                    } elseif ($this->modelLibraryMatches !== null) {
                        $matches = $this->modelLibraryMatches->findMatchesByRecognitionResult($result, 10);
                        if ($matches !== []) {
                            $result['library_matches'] = array_map(static function (array $match): array {
                                unset($match['_record']);
                                return $match;
                            }, $matches);
                            if (!isset($result['recommended_next_step']) || !is_array($result['recommended_next_step'])) {
                                $result['recommended_next_step'] = [];
                            }
                            $result['recommended_next_step']['path'] = 'model_library_candidate_matches';
                            $result['recommended_next_step']['reason'] = 'Ho trovato modelli già associati a un oggetto riconosciuto come compatibile.';
                        }
                    }
                }
            }

            if ($this->marketplaceModelSearch !== null && !isset($result['library_matches'])) {
                $result = $this->appendMarketplaceSearch($case, $job->id, $result);
            }

            $completed = $this->recognitionJobs->complete($job->id, $result);
            $this->eventBus->publish(new AIRecognitionCompleted(
                $repairCaseId,
                $completed->id,
                (float) ($result['object_guess']['confidence'] ?? 0),
                (string) ($result['recommended_next_step']['path'] ?? 'ask_more_photos'),
                gmdate('c')
            ));

            return $completed->toArray();
        } catch (\Throwable $exception) {
            $failed = $this->recognitionJobs->fail($job->id, $exception->getMessage());
            return $failed->toArray();
        }
    }




    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function reusedResult(array $result): array
    {
        $result['recognition_mode'] = 'reused_image_match';
        $result['source'] = 'reborn_library_cache';

        if (!isset($result['recommended_next_step']) || !is_array($result['recommended_next_step'])) {
            $result['recommended_next_step'] = [];
        }
        $result['recommended_next_step']['path'] = (string) ($result['recommended_next_step']['path'] ?? 'identify');
        $result['recommended_next_step']['reason'] = 'Ho riutilizzato un riconoscimento già completato per la stessa immagine.';

        $result['repair_notes'] = array_values(array_unique(array_merge(
            is_array($result['repair_notes'] ?? null) ? $result['repair_notes'] : [],
            ['Risultato recuperato dalla cache immagini Re-born.']
        )));

        return $result;
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function appendMarketplaceSearch(RepairCase $case, string $recognitionJobId, array $result): array
    {
        try {
            $result['marketplace_search'] = $this->marketplaceModelSearch?->search($case->id, $recognitionJobId, $result) ?? [
                'enabled' => false,
                'status' => 'not_configured',
            ];
        } catch (\Throwable $exception) {
            $result['marketplace_search'] = [
                'enabled' => true,
                'status' => 'search_error',
                'message' => 'La ricerca dei modelli 3D non ha risposto. Puoi comunque inviare una richiesta assistita o chiedere la stampa.',
                'error' => $exception->getMessage(),
                'next_step' => 'assisted_request_if_no_model_match',
            ];
            $result['repair_notes'][] = 'Marketplace model search failed silently: ' . $exception->getMessage();
        }

        return $result;
    }

    /** @param list<RepairAttachment> $attachments @return array<string, mixed> */
    private function providerUnavailableResult(RepairCase $case, array $attachments, string $reason): array
    {
        return [
            'recognition_mode' => 'live_provider_unavailable',
            'ai_provider' => [
                'provider' => 'configured_backend_provider',
                'status' => 'error_fallback',
                'error' => $reason,
                'image_count' => count($attachments),
            ],
            'identification' => [
                'status' => 'unclear',
                'source_image_type' => 'unknown',
                'visible_text' => [],
                'part_number' => 'id non riconosciuta',
                'commercial_name' => '',
                'possible_brands' => [],
                'possible_models' => [],
                'external_lookup_summary' => '',
                'why' => 'Il backend ha ricevuto le immagini ma il provider AI live non ha restituito un risultato utilizzabile.',
            ],
            'part_spec' => [
                'name_it' => 'id non riconosciuta',
                'name_en' => 'unrecognized id',
                'appliance_context' => '',
                'known_dimensions' => [],
                'key_features' => [],
                'compatibility_clues' => [],
                'manufacturing_features' => [],
            ],
            'object_guess' => [
                'label' => 'id non riconosciuta',
                'confidence' => 0.0,
                'object_context' => '',
            ],
            'damage_assessment' => [
                'type' => 'unknown',
                'severity' => 'review',
                'repairability_score' => 0.0,
            ],
            'replacement_part_brief' => [
                'plain_language_summary' => 'Riconoscimento non effettuato: il provider AI live non ha risposto con un risultato.',
                'probable_function' => '',
                'part_family' => '',
                'manufacturing_candidate' => false,
                'material_hint' => '',
                'critical_dimensions' => [],
                'photo_requirements' => ['foto laterale', 'foto del pezzo montato', 'foto con una misura visibile'],
                'user_questions' => [],
            ],
            'recommended_next_step' => [
                'path' => 'assisted_request',
                'reason' => 'L\'analisi automatica non e al momento disponibile. Riprova piu tardi oppure invia una richiesta assistita.',
            ],
            'suggested_inputs' => ['Carica una foto laterale, del pezzo montato o con una misura visibile.'],
            'repair_notes' => ['No mock recognition was used for the normal live UI flow.'],
        ];
    }

    /** @param list<RepairAttachment> $attachments @return array<string, mixed> */
    private function deterministicSmokeResult(RepairCase $case, array $attachments): array
    {
        $result = $this->mockResult($case, $attachments);
        $result['recognition_mode'] = 'deterministic_smoke';
        $result['ai_provider'] = [
            'provider' => 'deterministic_smoke',
            'status' => 'ci_safe_no_external_ai_call',
            'mode' => 'mock_result',
            'note' => 'CI smoke mode bypasses live providers so the generic upload pipeline test remains fast, deterministic and free from API quota/network dependencies.',
        ];
        $result['repair_notes'][] = 'CI deterministic smoke recognition was used; run debug-ai-vision-quality-live.ps1 for real provider quality validation.';

        return $result;
    }

    /** @param list<RepairAttachment> $attachments @return array<string, mixed> */
    private function mockResult(RepairCase $case, array $attachments): array
    {
        $text = strtolower($case->title . ' ' . $case->description . ' ' . $case->category . ' ' . implode(' ', array_map(
            static fn(RepairAttachment $attachment): string => $attachment->originalFilename . ' ' . $attachment->mimeType,
            $attachments
        )));

        $imageCount = count(array_filter($attachments, static fn(RepairAttachment $attachment): bool => str_starts_with($attachment->mimeType, 'image/')));
        $cadCount = count(array_filter($attachments, static function (RepairAttachment $attachment): bool {
            $extension = strtolower(pathinfo($attachment->originalFilename, PATHINFO_EXTENSION));
            return in_array($extension, ['stl', 'step', 'stp', 'obj'], true);
        }));
        $hasManual = (bool) array_filter($attachments, static fn(RepairAttachment $attachment): bool => $attachment->mimeType === 'application/pdf');

        $label = match (true) {
            str_contains($text, 'hinge') => 'hinge / plastic joint',
            str_contains($text, 'knob') => 'appliance knob / control interface',
            str_contains($text, '165314') || (str_contains($text, 'dishwasher') && str_contains($text, 'wheel')) || (str_contains($text, 'lavastoviglie') && str_contains($text, 'ruota')) => 'dishwasher lower rack wheel / basket roller',
            str_contains($text, 'wheel') => 'appliance basket wheel / rolling component',
            str_contains($text, 'cover') || str_contains($text, 'case') => 'plastic cover / wearable case',
            str_contains($text, 'consumer_electronics') => 'consumer electronics shell / small plastic part',
            default => 'repairable component / unknown object family',
        };

        $damageType = match (true) {
            str_contains($text, 'missing') || str_contains($text, 'lost') => 'missing_part',
            str_contains($text, 'crack') || str_contains($text, 'cracked') => 'cracked_shell',
            str_contains($text, 'worn') || str_contains($text, 'wear') => 'worn_component',
            str_contains($text, 'broken') || str_contains($text, 'rotto') => 'broken_part',
            default => 'unknown',
        };

        $confidence = min(0.91, 0.54 + ($imageCount * 0.08) + ($cadCount * 0.12) + ($hasManual ? 0.07 : 0));
        $repairability = min(0.94, 0.62 + ($imageCount * 0.06) + ($cadCount * 0.1));

        $path = match (true) {
            $imageCount < 2 && $cadCount === 0 => 'ask_more_photos',
            $cadCount > 0 => 'identify_part',
            str_contains($case->category, 'home_appliance') => 'find_provider',
            default => 'generate_part',
        };

        $reason = match ($path) {
            'ask_more_photos' => 'The MVP recognition engine needs at least one additional angle before a safe repair path can be ranked.',
            'identify_part' => 'The uploaded CAD/manual evidence can be matched against the Repair Knowledge Graph before production.',
            'find_provider' => 'The object appears repairable, but provider validation should confirm dimensions, material and safety constraints.',
            default => 'No verified part is available yet, so an AI-generated draft may be useful after dimensional validation.',
        };

        return [
            'recognition_mode' => 'deterministic_fallback_no_openai_key',
            'ai_provider' => [
                'provider' => 'openai',
                'status' => 'not_configured_fallback',
                'mode' => 'mock_result',
            ],
            'identification' => [
                'status' => str_contains($label, 'unknown') ? 'needs_more_images' : 'recognized',
                'source_image_type' => 'unknown',
                'visible_text' => [],
                'part_number' => str_contains($text, '165314') ? '165314' : '',
                'commercial_name' => str_contains($text, '165314') ? 'Dishwasher Lower Rack Wheel' : '',
                'possible_brands' => [],
                'possible_models' => [],
                'external_lookup_summary' => '',
                'why' => 'Fallback locale basato su testo richiesta, nomi file e tipo allegati. Non sostituisce il riconoscimento vision live e non può leggere OCR dalla foto.',
            ],
            'part_spec' => [
                'name_it' => $this->italianLabelFor($label),
                'name_en' => $label,
                'appliance_context' => str_contains($case->category, 'home_appliance') ? 'elettrodomestico' : 'oggetto da riparare',
                'known_dimensions' => [],
                'key_features' => [],
                'compatibility_clues' => [],
                'manufacturing_features' => [],
            ],
            'object_guess' => [
                'label' => $this->italianLabelFor($label),
                'confidence' => round($confidence, 2),
                'object_context' => 'Fallback ricavato da testo richiesta, nomi file e tipo allegati.',
            ],
            'damage_assessment' => [
                'type' => $damageType,
                'severity' => $confidence >= 0.78 ? 'medium' : 'high',
                'repairability_score' => round($repairability, 2),
            ],
            'replacement_part_brief' => [
                'plain_language_summary' => 'Riconoscimento locale limitato: senza OpenAI Vision live Re-born non può leggere testi, marca, modello o codice dalla foto. Questo brief è solo una traccia sicura per chiedere le informazioni mancanti.',
                'probable_function' => $this->probableFunctionForLabel($label),
                'part_family' => $label,
                'manufacturing_candidate' => $path !== 'find_provider',
                'material_hint' => 'PETG/ASA/TPU/nylon to be selected after load, heat, water and flexibility constraints are known.',
                'critical_dimensions' => ['larghezza totale', 'altezza totale', 'spessore', 'diametri o geometrie di fori, clip, cerniere o innesti'],
                'photo_requirements' => ['foto frontale nitida', 'foto laterale', 'foto del pezzo montato', 'foto con righello o calibro', 'foto ravvicinata di codici o scritte'],
                'user_questions' => ['Che cosa fa il pezzo quando l’oggetto funziona?', 'È esposto ad acqua, calore, carico o movimento ripetuto?', 'Ci sono codici, marca o modello leggibili sull’oggetto?'],
            ],
            'recommended_next_step' => [
                'path' => $path,
                'reason' => $reason,
            ],
            'suggested_inputs' => [
                'Add one photo from the side',
                'Measure the broken part width',
                'Upload any existing CAD or manual',
            ],
            'repair_notes' => [
                'Questa è una diagnosi AI preliminare.',
                'La producibilità finale deve essere verificata prima della produzione.',
            ],
        ];
    }

    private function italianLabelFor(string $label): string
    {
        $value = strtolower($label);
        return match (true) {
            str_contains($value, 'hinge') || str_contains($value, 'joint') => 'cerniera / snodo plastico',
            str_contains($value, 'knob') || str_contains($value, 'control') => 'pomello / comando elettrodomestico',
            str_contains($value, 'dishwasher') && str_contains($value, 'wheel') => 'ruota cestello inferiore lavastoviglie',
            str_contains($value, 'wheel') || str_contains($value, 'rolling') => 'ruota cestello / componente di scorrimento',
            str_contains($value, 'cover') || str_contains($value, 'case') || str_contains($value, 'shell') => 'cover / scocca plastica',
            default => 'componente da confermare',
        };
    }

    private function probableFunctionForLabel(string $label): string
    {
        $value = strtolower($label);
        return match (true) {
            str_contains($value, 'hinge') || str_contains($value, 'joint') => 'Allows two parts to rotate, open or close while staying aligned.',
            str_contains($value, 'knob') || str_contains($value, 'control') => 'Transfers hand input to a control shaft, switch or selector.',
            str_contains($value, 'dishwasher') && str_contains($value, 'wheel') => 'Permette al cestello inferiore della lavastoviglie di scorrere correttamente sulle guide.',
            str_contains($value, 'wheel') || str_contains($value, 'rolling') => 'Permette a un cestello, cassetto o elemento mobile di scorrere o rotolare correttamente.',
            str_contains($value, 'cover') || str_contains($value, 'case') || str_contains($value, 'shell') => 'Protects internal components or restores the external shape of the object.',
            default => 'Mechanical or protective function to be confirmed with one more photo and measurements.',
        };
    }
}
