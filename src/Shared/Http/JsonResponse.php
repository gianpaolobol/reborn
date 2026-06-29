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

    public static function ok(array $payload = []): self
    {
        return new self(['success' => true] + $payload, 200);
    }

    public static function created(array $payload = []): self
    {
        return new self(['success' => true] + $payload, 201);
    }

    public static function validation(array $errors): self
    {
        return new self([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The request contains invalid or missing fields.',
                'fields' => $errors,
            ],
        ], 422);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => $message,
            ],
        ], 404);
    }

    public static function serverError(string $message = 'Unexpected server error.'): self
    {
        return new self([
            'success' => false,
            'error' => [
                'code' => 'SERVER_ERROR',
                'message' => $message,
            ],
        ], 500);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Reborn-API: Repair-Intelligence-v1');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
