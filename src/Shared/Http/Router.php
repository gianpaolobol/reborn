<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

use Throwable;

final class Router
{
    /** @var list<array{method:string, pattern:string, regex:string, params:list<string>, handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        [$regex, $params] = $this->compile($pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
        ];
    }

    public function dispatch(): JsonResponse
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $matches[$name] ?? null;
            }

            try {
                $response = ($route['handler'])(Request::fromGlobals($params));
                return $response instanceof JsonResponse ? $response : JsonResponse::ok(['data' => $response]);
            } catch (Throwable $exception) {
                if (($_ENV['APP_DEBUG'] ?? 'true') === 'true') {
                    return JsonResponse::serverError($exception->getMessage());
                }

                return JsonResponse::serverError();
            }
        }

        return JsonResponse::notFound('API route not found.');
    }

    /** @return array{string, list<string>} */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $matches) use (&$params): string {
            $params[] = $matches[1];
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, rtrim($pattern, '/') ?: '/');

        return ['#^' . $regex . '$#', $params];
    }
}
