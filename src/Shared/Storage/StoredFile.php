<?php

declare(strict_types=1);

namespace Reborn\Shared\Storage;

final class StoredFile
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $sha256,
    ) {
    }
}
