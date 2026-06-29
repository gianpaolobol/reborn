<?php

declare(strict_types=1);

namespace Reborn\Shared\Http;

use Throwable;

final class Router
{
    public function __construct(private readonly ?RateLimiter $rateLimiter = null)
    {
    }

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
        $methodMatches = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $methodMatches[] = $route['method'];
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $matches[$name] ?? null;
            }

            $request = Request::fromGlobals($params);
            if ($request->jsonError() !== null) {
                return JsonResponse::badRequest('Malformed JSON body.', ['json_error' => $request->jsonError()], $request->requestId());
            }

            $rateLimitResponse = $this->rateLimiter?->enforce($request);
            if ($rateLimitResponse instanceof JsonResponse) {
                return $rateLimitResponse;
            }

            try {
                $response = ($route['handler'])($request);
                return $response instanceof JsonResponse ? $response : JsonResponse::ok(['data' => $response], $request->requestId());
            } catch (ValidationException $exception) {
                $fields = $exception->details()['fields'] ?? [];
                return JsonResponse::validation(is_array($fields) ? $fields : [], $request->requestId());
            } catch (ApiException $exception) {
                return JsonResponse::error(
                    $exception->errorCode(),
                    $exception->getMessage(),
                    $exception->statusCode(),
                    $exception->details(),
                    $request->requestId()
                );
            } catch (Throwable $exception) {
                $this->logException($exception, $request);
                if (($_ENV['APP_DEBUG'] ?? 'true') === 'true') {
                    return JsonResponse::serverError($exception->getMessage(), $request->requestId());
                }

                return JsonResponse::serverError('Unexpected server error.', $request->requestId());
            }
        }

        $request = Request::fromGlobals();
        if ($methodMatches !== []) {
            return JsonResponse::error('METHOD_NOT_ALLOWED', 'HTTP method not allowed for this route.', 405, [
                'allowed_methods' => array_values(array_unique($methodMatches)),
            ], $request->requestId());
        }

        return JsonResponse::notFound('API route not found.', $request->requestId());
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

    private function logException(Throwable $exception, Request $request): void
    {
        $root = dirname(__DIR__, 3);
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $line = json_encode([
            'occurred_at' => gmdate('c'),
            'request_id' => $request->requestId(),
            'method' => $request->method(),
            'path' => $request->path(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        file_put_contents($logDir . '/api-' . gmdate('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND);
    }
}
