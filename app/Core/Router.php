<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array{0: class-string, 1: string}> */
    private array $routes = [];

    /**
     * @param class-string $controllerClass
     */
    public function get(string $path, string $controllerClass, string $action): self
    {
        return $this->map('GET', $path, $controllerClass, $action);
    }

    /**
     * @param class-string $controllerClass
     */
    public function post(string $path, string $controllerClass, string $action): self
    {
        return $this->map('POST', $path, $controllerClass, $action);
    }

    /**
     * @param class-string $controllerClass
     */
    private function map(string $method, string $path, string $controllerClass, string $action): self
    {
        $key = strtoupper($method) . ' ' . $this->normalize($path);
        $this->routes[$key] = [$controllerClass, $action];
        return $this;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');
        $key = strtoupper($method) . ' ' . $path;

        if (isset($this->routes[$key])) {
            [$class, $action] = $this->routes[$key];
            $controller = new $class();
            $controller->{$action}();
            return;
        }

        http_response_code(404);
        echo '404 — rota não encontrada';
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
