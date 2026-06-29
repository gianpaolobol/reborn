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
     * @param array<string, string> $headers
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
        private readonly array $headers = [],
    ) {
    }

    public static function fromGlobals(array $routeParams = []): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $rawBody = file_get_contents('php://input') ?: '';
        $rawBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody) ?? $rawBody;
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

        if ($_POST !== [] && !str_contains($contentType, 'application/json')) {
            $decodedBody = $_POST;
        }

        $headers = self::normalizeHeaders($_SERVER);
        $requestId = $headers['x-request-id'] ?? '';
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
            $requestId,
            $headers
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

    /** @param array<string, mixed> $server @return array<string, string> */
    private static function normalizeHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }

        return $headers;
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

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $authorization = trim((string) $this->header('authorization', ''));
        if ($authorization === '') {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $token = trim($matches[1]);
        return $token === '' ? null : $token;
    }

    public function ipAddress(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        return $this->header('user-agent');
    }
}
