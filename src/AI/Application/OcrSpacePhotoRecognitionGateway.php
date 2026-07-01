<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairAttachment;
use Reborn\Repair\Domain\RepairCase;

final class OcrSpacePhotoRecognitionGateway extends AbstractCloudPhotoRecognitionGateway
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $cfg = $this->providerConfig();
        $enabled = (bool) ($cfg['enabled'] ?? true);
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));

        return [
            'provider' => 'ocrspace',
            'capability' => 'cloud_ocr_text_and_part_code_extraction',
            'enabled' => $enabled,
            'configured' => $enabled && $apiKey !== '',
            'mode' => $enabled && $apiKey !== '' ? 'live_ocrspace_api' : 'not_configured',
            'model' => 'OCR.space engine ' . (string) ($cfg['ocr_engine'] ?? '2'),
            'base_url' => (string) ($cfg['base_url'] ?? 'https://api.ocr.space/parse/image'),
            'quality_profile' => 'cloud_free_ocr_first_reference_part_identification_v1',
            'billing_note' => 'OCR.space Free API is useful for demo OCR/code extraction but remains rate-limited by the provider.',
            'missing_configuration' => $enabled && $apiKey === '' ? ['OCRSPACE_API_KEY'] : [],
        ];
    }

    public function analyze(RepairCase $case, array $attachments): ?array
    {
        $cfg = $this->providerConfig();
        if ((bool) ($cfg['enabled'] ?? true) !== true || trim((string) ($cfg['api_key'] ?? '')) === '') {
            return null;
        }

        $path = $this->firstImagePath($attachments);
        if ($path === null) {
            return null;
        }

        $model = 'ocrspace-engine-' . (string) ($cfg['ocr_engine'] ?? '2');
        try {
            $response = $this->postForm((string) ($cfg['base_url'] ?? 'https://api.ocr.space/parse/image'), [
                'apikey' => (string) $cfg['api_key'],
                'base64Image' => $this->dataUrlForPath($path),
                'language' => (string) ($cfg['language'] ?? 'eng'),
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true',
                'OCREngine' => (int) ($cfg['ocr_engine'] ?? 2),
            ], [], (int) ($cfg['timeout_seconds'] ?? 45));

            if ($response['status'] >= 400) {
                return $this->errorFallback('fallback_after_ocrspace_error', 'ocrspace', $model, $attachments, 'OCR.space returned HTTP ' . $response['status'] . ': ' . $this->safeSubstring($response['body'], 0, 500));
            }

            $json = $this->decodeJsonResponse($response['body']);
            if ((bool) ($json['IsErroredOnProcessing'] ?? false)) {
                $message = $json['ErrorMessage'] ?? $json['ErrorDetails'] ?? 'OCR.space processing error.';
                return $this->errorFallback('fallback_after_ocrspace_error', 'ocrspace', $model, $attachments, is_array($message) ? implode('; ', array_map('strval', $message)) : (string) $message);
            }

            $lines = [];
            foreach (($json['ParsedResults'] ?? []) as $result) {
                if (!is_array($result)) { continue; }
                $parsedText = (string) ($result['ParsedText'] ?? '');
                foreach (preg_split('/\R+/', $parsedText) ?: [] as $line) {
                    $line = trim((string) $line);
                    if ($line !== '') { $lines[] = $line; }
                }
            }

            if ($lines === []) {
                return $this->errorFallback('fallback_after_ocrspace_no_text', 'ocrspace', $model, $attachments, 'OCR.space did not read useful text from the image.');
            }

            return $this->resultFromVisibleText(
                'ocrspace_api',
                'ocrspace',
                'live_response',
                $model,
                $attachments,
                $lines,
                'OCR.space ha letto testo/codici nell’immagine e Re-born li ha trasformati in un brief ricambio preliminare.'
            );
        } catch (\Throwable $e) {
            return $this->errorFallback('fallback_after_ocrspace_error', 'ocrspace', $model, $attachments, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function providerConfig(): array
    {
        return is_array($this->config['ocrspace'] ?? null) ? $this->config['ocrspace'] : [];
    }
}
