<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string|null> $routeParams
     * @param array<string, mixed> $files
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $routeParams = [],
        private readonly array $files = [],
        private readonly ?string $jsonError = null,
        private readonly string $requestId = '',
    ) {
    }

    public static function fromGlobals(array $routeParams = []): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $rawBody = file_get_contents('php://input') ?: '';
        $decodedBody = [];
        $jsonError = null;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

        if ($rawBody !== '' && str_contains($contentType, 'application/json')) {
            $decodedBody = json_decode($rawBody, true);
            if (!is_array($decodedBody)) {
                $decodedBody = [];
                $jsonError = json_last_error_msg();
            }
        }

        if ($rawBody !== '' && $contentType === '') {
            $maybeJson = json_decode($rawBody, true);
            if (is_array($maybeJson)) {
                $decodedBody = $maybeJson;
            }
        }

        $requestId = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(8));
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rtrim($path, '/') ?: '/',
            $_GET,
            $decodedBody,
            $routeParams,
            self::normalizeFiles($_FILES),
            $jsonError,
            $requestId
        );
    }

    /** @param array<string, mixed> $files @return array<string, mixed> */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $field => $file) {
            if (!is_array($file)) {
                continue;
            }

            $normalized[$field] = $file;
        }

        return $normalized;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function jsonError(): ?string
    {
        return $this->jsonError;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) ? $file : null;
    }
}
