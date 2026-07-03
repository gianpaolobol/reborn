<?php

declare(strict_types=1);

namespace Reborn\AI\Application;

use Reborn\Repair\Domain\RepairCase;

final class AwsRekognitionCustomLabelsGateway extends AbstractCloudPhotoRecognitionGateway
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $cfg = $this->providerConfig();
        $enabled = (bool) ($cfg['enabled'] ?? false);
        $accessKey = trim((string) ($cfg['access_key_id'] ?? ''));
        $secretKey = trim((string) ($cfg['secret_access_key'] ?? ''));
        $region = trim((string) ($cfg['region'] ?? ''));
        $projectVersionArn = trim((string) ($cfg['project_version_arn'] ?? ''));
        $missing = [];
        if ($enabled && $accessKey === '') { $missing[] = 'AWS_REKOGNITION_ACCESS_KEY_ID'; }
        if ($enabled && $secretKey === '') { $missing[] = 'AWS_REKOGNITION_SECRET_ACCESS_KEY'; }
        if ($enabled && $region === '') { $missing[] = 'AWS_REKOGNITION_REGION'; }
        if ($enabled && $projectVersionArn === '') { $missing[] = 'AWS_REKOGNITION_PROJECT_VERSION_ARN'; }

        return [
            'provider' => 'awsrekognition',
            'capability' => 'specialized_custom_labels_part_identification',
            'enabled' => $enabled,
            'configured' => $enabled && $missing === [],
            'mode' => $enabled && $missing === [] ? 'live_aws_rekognition_custom_labels' : 'not_configured',
            'model' => $projectVersionArn !== '' ? $projectVersionArn : 'rekognition-custom-labels-project-version',
            'base_url' => $this->endpoint($cfg),
            'quality_profile' => 'trainable_custom_labels_specific_part_detection_v1',
            'training_note' => 'Addestra il modello in Amazon Rekognition Custom Labels e inserisci qui il ProjectVersionArn avviato.',
            'missing_configuration' => $missing,
        ];
    }

    public function analyze(RepairCase $case, array $attachments): ?array
    {
        $cfg = $this->providerConfig();
        if ((bool) ($cfg['enabled'] ?? false) !== true) {
            return null;
        }

        $path = $this->firstImagePath($attachments);
        if ($path === null) {
            return null;
        }

        $status = $this->status();
        if ((bool) ($status['configured'] ?? false) !== true) {
            return null;
        }

        $projectVersionArn = (string) $cfg['project_version_arn'];
        $model = $projectVersionArn;
        try {
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new \RuntimeException('Unable to read image file for AWS Rekognition request.');
            }

            $payload = [
                'ProjectVersionArn' => $projectVersionArn,
                'Image' => ['Bytes' => base64_encode($bytes)],
                'MaxResults' => (int) ($cfg['max_results'] ?? 10),
                'MinConfidence' => (float) ($cfg['min_confidence'] ?? 55),
            ];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                throw new \RuntimeException('Unable to encode AWS Rekognition payload.');
            }

            $response = $this->postAwsJson($this->endpoint($cfg), $json, $cfg, 'RekognitionService.DetectCustomLabels', (int) ($cfg['timeout_seconds'] ?? 45));
            if ($response['status'] >= 400) {
                return $this->errorFallback('fallback_after_awsrekognition_error', 'awsrekognition', $model, $attachments, 'AWS Rekognition returned HTTP ' . $response['status'] . ': ' . $this->safeSubstring($response['body'], 0, 800));
            }

            $decoded = $this->decodeJsonResponse($response['body']);
            $predictions = [];
            foreach (($decoded['CustomLabels'] ?? []) as $label) {
                if (!is_array($label)) { continue; }
                $predictions[] = [
                    'label' => (string) ($label['Name'] ?? ''),
                    'confidence' => (float) ($label['Confidence'] ?? 0.0),
                    'source' => 'awsrekognition',
                    'raw' => $label,
                ];
            }

            if ($predictions === []) {
                return $this->errorFallback('fallback_after_awsrekognition_no_labels', 'awsrekognition', $model, $attachments, 'AWS Rekognition Custom Labels did not return labels above threshold.');
            }

            return SpecializedVisionResultFactory::resultFromPredictions($predictions, $attachments, [
                'provider' => 'awsrekognition',
                'recognition_mode' => 'aws_rekognition_custom_labels_api',
                'provider_status' => 'live_response',
                'model' => $model,
                'min_confidence' => ((float) ($cfg['min_confidence'] ?? 55)) / 100.0,
                'possible_brands' => (string) ($cfg['possible_brands'] ?? ''),
                'appliance_context' => (string) ($cfg['appliance_context'] ?? 'Da confermare.'),
                'training_note' => 'Amazon Rekognition Custom Labels: usare dataset etichettato e ProjectVersionArn del modello avviato.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorFallback('fallback_after_awsrekognition_error', 'awsrekognition', $model, $attachments, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function providerConfig(): array
    {
        return is_array($this->config['awsrekognition'] ?? null) ? $this->config['awsrekognition'] : [];
    }

    /** @param array<string, mixed> $cfg */
    private function endpoint(array $cfg): string
    {
        $base = trim((string) ($cfg['base_url'] ?? ''));
        if ($base !== '') {
            return rtrim($base, '/');
        }
        $region = trim((string) ($cfg['region'] ?? 'us-east-1'));
        return 'https://rekognition.' . $region . '.amazonaws.com';
    }

    /** @param array<string, mixed> $cfg @return array{status:int, body:string} */
    private function postAwsJson(string $url, string $body, array $cfg, string $target, int $timeout): array
    {
        $region = (string) ($cfg['region'] ?? 'us-east-1');
        $accessKey = (string) ($cfg['access_key_id'] ?? '');
        $secretKey = (string) ($cfg['secret_access_key'] ?? '');
        $sessionToken = trim((string) ($cfg['session_token'] ?? ''));
        $service = 'rekognition';
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new \RuntimeException('Invalid AWS Rekognition endpoint.');
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);
        $headersToSign = [
            'content-type' => 'application/x-amz-json-1.1',
            'host' => $host,
            'x-amz-date' => $amzDate,
            'x-amz-target' => $target,
        ];
        if ($sessionToken !== '') {
            $headersToSign['x-amz-security-token'] = $sessionToken;
        }
        ksort($headersToSign);
        $canonicalHeaders = '';
        foreach ($headersToSign as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headersToSign));
        $canonicalRequest = "POST\n/\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
        $signingKey = $this->awsSigningKey($secretKey, $dateStamp, $region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $headers = [
            'Content-Type: application/x-amz-json-1.1',
            'X-Amz-Date: ' . $amzDate,
            'X-Amz-Target: ' . $target,
            'Authorization: ' . $authorization,
        ];
        if ($sessionToken !== '') {
            $headers[] = 'X-Amz-Security-Token: ' . $sessionToken;
        }

        return $this->postRaw($url, $body, $headers, $timeout);
    }

    private function awsSigningKey(string $secretKey, string $dateStamp, string $region, string $service): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
