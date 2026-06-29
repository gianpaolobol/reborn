<?php

declare(strict_types=1);

namespace Reborn\Repair\Infrastructure;

use PDO;
use Reborn\Repair\Domain\RepairAttachment;
use Reborn\Repair\Domain\RepairAttachmentRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteRepairAttachmentRepository implements RepairAttachmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByRepairCase(string $repairCaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM repair_attachments WHERE repair_case_id = :repair_case_id ORDER BY created_at DESC');
        $stmt->execute(['repair_case_id' => $repairCaseId]);

        return array_map(static fn(array $row): RepairAttachment => RepairAttachment::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(array $data): RepairAttachment
    {
        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO repair_attachments (id, repair_case_id, original_filename, stored_path, mime_type, size_bytes, sha256, kind, created_at) VALUES (:id, :repair_case_id, :original_filename, :stored_path, :mime_type, :size_bytes, :sha256, :kind, :created_at)');
        $stmt->execute([
            'id' => $id,
            'repair_case_id' => $data['repair_case_id'],
            'original_filename' => $data['original_filename'],
            'stored_path' => $data['stored_path'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
            'sha256' => $data['sha256'],
            'kind' => $data['kind'] ?? 'repair_asset',
            'created_at' => $now,
        ]);

        $stmt = $this->pdo->prepare('SELECT * FROM repair_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return RepairAttachment::fromRow($stmt->fetch(PDO::FETCH_ASSOC));
    }
}
