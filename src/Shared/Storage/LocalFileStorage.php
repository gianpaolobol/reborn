<?php

declare(strict_types=1);

namespace Reborn\Shared\Storage;

use Reborn\Shared\Http\BadRequestException;
use Reborn\Shared\Support\Uuid;

final class LocalFileStorage
{
    public function __construct(private readonly string $rootPath)
    {
    }

    /** @param array<string, mixed> $file */
    public function storeRepairAsset(string $repairCaseId, array $file): StoredFile
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new BadRequestException('Uploaded file cannot be read.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $safeExtension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $directory = 'repair-cases/' . preg_replace('/[^a-zA-Z0-9\-]/', '', $repairCaseId);
        $absoluteDirectory = rtrim($this->rootPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);

        if (!is_dir($absoluteDirectory)) {
            mkdir($absoluteDirectory, 0775, true);
        }

        $filename = Uuid::v4() . '.' . $safeExtension;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

        if (is_uploaded_file($tmpName)) {
            $stored = move_uploaded_file($tmpName, $absolutePath);
        } else {
            $stored = rename($tmpName, $absolutePath) || copy($tmpName, $absolutePath);
        }

        if (!$stored || !is_file($absolutePath)) {
            throw new BadRequestException('Uploaded file could not be stored.');
        }

        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';

        return new StoredFile(
            $directory . '/' . $filename,
            $mimeType,
            filesize($absolutePath) ?: 0,
            hash_file('sha256', $absolutePath) ?: ''
        );
    }
}
