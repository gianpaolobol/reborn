<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class JsonResponse
{
    public function __construct(
        private readonly array $payload,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function ok(array $payload = [], ?string $requestId = null): self
    {
        return new self(self::withMeta(['success' => true] + $payload, $requestId), 200);
    }

    public static function created(array $payload = [], ?string $requestId = null): self
    {
        return new self(self::withMeta(['success' => true] + $payload, $requestId), 201);
    }

    /** @param array<string, list<string>> $errors */
    public static function validation(array $errors, ?string $requestId = null): self
    {
        return self::error('VALIDATION_ERROR', 'The request contains invalid or missing fields.', 422, ['fields' => $errors], $requestId);
    }

    public static function badRequest(string $message = 'Bad request.', array $details = [], ?string $requestId = null): self
    {
        return self::error('BAD_REQUEST', $message, 400, $details, $requestId);
    }

    public static function notFound(string $message = 'Resource not found.', ?string $requestId = null): self
    {
        return self::error('NOT_FOUND', $message, 404, [], $requestId);
    }

    /** @param array<string, mixed> $details */
    public static function error(string $code, string $message, int $status = 500, array $details = [], ?string $requestId = null): self
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return new self(self::withMeta($payload, $requestId), $status);
    }

    public static function serverError(string $message = 'Unexpected server error.', ?string $requestId = null): self
    {
        return self::error('SERVER_ERROR', $message, 500, [], $requestId);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private static function withMeta(array $payload, ?string $requestId): array
    {
        if ($requestId !== null && $requestId !== '') {
            $payload['meta'] = ['request_id' => $requestId];
        }

        return $payload;
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Reborn-API: Repair-Intelligence-v1');
        SecurityHeaders::applyApi();

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
