<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router simples.
 *
 * Registra rotas GET/POST associando um caminho (ex.: "/") a um par
 * "Controller@metodo" e resolve a requisição atual.
 */
final class Router
{
    /** @var array<string, array<string, string>> */
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    public function get(string $path, string $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, string $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    /**
     * Resolve o método HTTP + URI para um handler "Controller@metodo".
     * Retorna null quando a rota não existe (404).
     */
    public function match(string $method, string $uri): ?string
    {
        $method = strtoupper($method);
        $path   = $this->normalize($uri);

        return $this->routes[$method][$path] ?? null;
    }

    /**
     * Normaliza o caminho: remove query string, barras duplicadas e a barra
     * final (exceto a raiz).
     */
    private function normalize(string $path): string
    {
        // Remove query string, se houver.
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
