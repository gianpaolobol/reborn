<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

final class RepairAttachment
{
    public function __construct(
        public readonly string $id,
        public readonly string $repairCaseId,
        public readonly string $originalFilename,
        public readonly string $storedPath,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $sha256,
        public readonly string $kind,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['repair_case_id'],
            (string) $row['original_filename'],
            (string) $row['stored_path'],
            (string) $row['mime_type'],
            (int) $row['size_bytes'],
            (string) $row['sha256'],
            (string) $row['kind'],
            (string) $row['created_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'repair_case_id' => $this->repairCaseId,
            'original_filename' => $this->originalFilename,
            'stored_path' => $this->storedPath,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
            'kind' => $this->kind,
            'created_at' => $this->createdAt,
        ];
    }
}
