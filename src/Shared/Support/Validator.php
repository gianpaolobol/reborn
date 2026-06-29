<?php

declare(strict_types=1);

namespace Reborn\Shared\Support;

final class Validator
{
    /** @param array<string, mixed> $data @param list<string> $required @return array<string, list<string>> */
    public static function required(array $data, array $required): array
    {
        $errors = [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || trim((string) $data[$field]) === '') {
                $errors[$field][] = $field . ' is required.';
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $data @return array<string, list<string>> */
    public static function repairCasePayload(array $data): array
    {
        $errors = self::required($data, ['title', 'description', 'category']);

        self::stringLength($errors, $data, 'title', 3, 120);
        self::stringLength($errors, $data, 'description', 10, 2000);
        self::stringLength($errors, $data, 'category', 2, 80);

        $allowedCategories = ['home_appliance', 'consumer_electronics', 'furniture', 'mobility', 'sport', 'tooling', 'generic'];
        if (isset($data['category']) && !in_array((string) $data['category'], $allowedCategories, true)) {
            $errors['category'][] = 'category must be one of: ' . implode(', ', $allowedCategories) . '.';
        }

        return $errors;
    }

    /** @param array<string, list<string>> $errors @param array<string, mixed> $data */
    private static function stringLength(array &$errors, array $data, string $field, int $min, int $max): void
    {
        if (!isset($data[$field])) {
            return;
        }

        $value = trim((string) $data[$field]);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length < $min) {
            $errors[$field][] = $field . ' must be at least ' . $min . ' characters.';
        }

        if ($length > $max) {
            $errors[$field][] = $field . ' must be no more than ' . $max . ' characters.';
        }
    }

    /** @param array<string, mixed>|null $file @return array<string, list<string>> */
    public static function uploadedRepairAsset(?array $file, int $maxBytes = 15728640): array
    {
        $errors = [];
        if ($file === null) {
            return ['file' => ['file is required.']];
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['file' => ['upload failed with code ' . $errorCode . '.']];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $errors['file'][] = 'file is empty.';
        }

        if ($size > $maxBytes) {
            $errors['file'][] = 'file exceeds the maximum size of ' . $maxBytes . ' bytes.';
        }

        $name = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'stl', 'step', 'stp', 'obj'];
        if (!in_array($extension, $allowedExtensions, true)) {
            $errors['file'][] = 'file extension must be one of: ' . implode(', ', $allowedExtensions) . '.';
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $detectedMime = '';
        if ($tmpName !== '' && is_file($tmpName) && function_exists('mime_content_type')) {
            $detectedMime = strtolower((string) (mime_content_type($tmpName) ?: ''));
        }
        $declaredMime = strtolower((string) ($file['type'] ?? ''));
        $mimeType = $detectedMime !== '' ? $detectedMime : $declaredMime;

        $imageMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $documentMimes = ['application/pdf'];
        $modelMimes = ['model/stl'];
        $cadExtensions = ['stl', 'step', 'stp', 'obj'];

        $mimeAllowed = in_array($mimeType, $imageMimes, true)
            || in_array($mimeType, $documentMimes, true)
            || in_array($mimeType, $modelMimes, true)
            || ($mimeType === 'application/octet-stream' && in_array($extension, $cadExtensions, true));

        if (!$mimeAllowed) {
            $errors['file'][] = 'file MIME type must be image/jpeg, image/png, image/webp, application/pdf, model/stl, or application/octet-stream for STL/STEP/STP/OBJ files.';
        }

        return $errors;
    }

}
