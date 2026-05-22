<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method) . ' ' . trim($path, '/')] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/');
        $path = preg_replace('#^store-hub/(public/)?#', '', $path);
        $key = strtoupper($method) . ' ' . trim($path, '/');

        if (!isset($this->routes[$key])) {
            json_response(['error' => 'Route not found'], 404);
        }

        ($this->routes[$key])();
    }
}
