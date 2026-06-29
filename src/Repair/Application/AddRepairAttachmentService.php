<?php

declare(strict_types=1);

namespace Reborn\Repair\Application;

use Reborn\Repair\Domain\RepairAttachmentAdded;
use Reborn\Repair\Domain\RepairAttachmentRepository;
use Reborn\Repair\Domain\RepairCaseRepository;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Storage\LocalFileStorage;
use Reborn\Shared\Support\Validator;

final class AddRepairAttachmentService
{
    public function __construct(
        private readonly RepairCaseRepository $repairCases,
        private readonly RepairAttachmentRepository $attachments,
        private readonly LocalFileStorage $storage,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @param array<string, mixed>|null $file */
    public function handle(string $repairCaseId, ?array $file, string $kind = 'repair_asset'): array
    {
        $case = $this->repairCases->find($repairCaseId);
        if ($case === null) {
            throw new NotFoundException('Repair case not found.');
        }

        $errors = Validator::uploadedRepairAsset($file);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $storedFile = $this->storage->storeRepairAsset($repairCaseId, $file);
        $attachment = $this->attachments->create([
            'repair_case_id' => $repairCaseId,
            'original_filename' => (string) ($file['name'] ?? 'upload.bin'),
            'stored_path' => $storedFile->relativePath,
            'mime_type' => $storedFile->mimeType,
            'size_bytes' => $storedFile->sizeBytes,
            'sha256' => $storedFile->sha256,
            'kind' => $kind,
        ]);

        $this->eventBus->publish(new RepairAttachmentAdded($repairCaseId, $attachment->id, $attachment->kind, gmdate('c')));

        return $attachment->toArray();
    }
}
