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
            $result = $this->mockResult($case, $selectedAttachments);
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
            'object_guess' => [
                'label' => $label,
                'confidence' => round($confidence, 2),
            ],
            'damage_assessment' => [
                'type' => $damageType,
                'severity' => $confidence >= 0.78 ? 'medium' : 'high',
                'repairability_score' => round($repairability, 2),
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
                'This is a preliminary AI diagnosis.',
                'Final manufacturability must be verified before production.',
            ],
        ];
    }
}
