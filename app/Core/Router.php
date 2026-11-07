<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    private array $routes = [];

    public function get(string $path, Closure|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, Closure|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function patch(string $path, Closure|array $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function add(string $method, string $path, Closure|array $handler): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler];
    }

    public function dispatch(Request $request): Response|HtmlResponse|RedirectResponse|FileResponse
    {
        foreach ($this->routes as [$method, $path, $handler]) {
            if ($method !== $request->method || !preg_match($this->pattern($path), $request->path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $name => $value) {
                if (is_string($name)) {
                    $params[$name] = $value;
                }
            }
            $request = $request->withParams($params);

            $result = $handler instanceof Closure
                ? $handler($request)
                : (is_object($handler[0])
                    ? $handler[0]->{$handler[1]}($request)
                    : (new $handler[0])->{$handler[1]}($request));

            return $result instanceof Response || $result instanceof HtmlResponse || $result instanceof RedirectResponse || $result instanceof FileResponse ? $result : new Response($result);
        }

        return Response::failure('NOT_FOUND', 'La ruta solicitada no existe.', 404);
    }

    private function pattern(string $path): string
    {
        $pattern = preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', '(?P<$1>[0-9]+)', $path);
        return '~^' . $pattern . '$~';
    }
}
