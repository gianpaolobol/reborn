<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

final class Request
{
    /** @param array<string, string> $routeParams */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $routeParams = [],
    ) {
    }

    public static function fromGlobals(array $routeParams = []): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $rawBody = file_get_contents('php://input') ?: '';
        $decodedBody = [];

        if ($rawBody !== '') {
            $decodedBody = json_decode($rawBody, true);
            if (!is_array($decodedBody)) {
                $decodedBody = [];
            }
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rtrim($path, '/') ?: '/',
            $_GET,
            $decodedBody,
            $routeParams
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
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
}
