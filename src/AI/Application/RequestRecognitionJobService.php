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
    ) {
    }

    /** @param list<string> $attachmentIds @return array<string, mixed> */
    public function handle(string $repairCaseId, string $requestedBy, array $attachmentIds): array
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
            $result = $this->photoRecognitionGateway->analyze($case, $selectedAttachments) ?? $this->mockResult($case, $selectedAttachments);
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
                'part_number' => '',
                'why' => 'Fallback locale basato su testo richiesta, nomi file e tipo allegati. Non sostituisce il riconoscimento vision live.',
            ],
            'part_spec' => [
                'name_it' => $this->italianLabelFor($label),
                'name_en' => $label,
                'appliance_context' => str_contains($case->category, 'home_appliance') ? 'elettrodomestico' : 'oggetto da riparare',
                'known_dimensions' => [],
                'key_features' => [],
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
                'plain_language_summary' => 'Re-born prepared a first replacement-part brief from the uploaded evidence. Add dimensions and a second angle before production.',
                'probable_function' => $this->probableFunctionForLabel($label),
                'part_family' => $label,
                'manufacturing_candidate' => $path !== 'find_provider',
                'material_hint' => 'PETG/ASA/TPU/nylon to be selected after load, heat, water and flexibility constraints are known.',
                'critical_dimensions' => ['overall width', 'overall height', 'thickness', 'mounting hole or clip dimensions if present'],
                'photo_requirements' => ['close-up of broken part', 'side view', 'full object context', 'photo with ruler or coin for scale'],
                'user_questions' => ['What does the part do when the object works?', 'Is the part exposed to heat, water, load or repeated movement?'],
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
            str_contains($value, 'wheel') || str_contains($value, 'rolling') => 'Lets a basket, drawer or moving element slide or roll correctly.',
            str_contains($value, 'cover') || str_contains($value, 'case') || str_contains($value, 'shell') => 'Protects internal components or restores the external shape of the object.',
            default => 'Mechanical or protective function to be confirmed with one more photo and measurements.',
        };
    }
}
