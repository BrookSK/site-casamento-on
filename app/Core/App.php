<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Bootstrap da aplicação (front controller lógico).
 *
 * Responsável por: carregar config, registrar o autoload de classes,
 * montar as rotas e despachar a requisição atual para o controller correto.
 */
final class App
{
    private array $config;
    private Router $router;

    public function __construct()
    {
        $this->config = require APP_PATH . '/Config/config.php';

        // Fuso horário / timezone a partir da config.
        if (!empty($this->config['app']['timezone'])) {
            date_default_timezone_set($this->config['app']['timezone']);
        }

        $this->registerAutoloader();

        $this->router = new Router();
        $this->registerRoutes();
    }

    /**
     * Autoloader PSR-4 simples: App\Foo\Bar -> app/Foo/Bar.php
     */
    private function registerAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }

    private function registerRoutes(): void
    {
        $router = $this->router;
        require APP_PATH . '/Routes/web.php';
    }

    /**
     * Executa a aplicação: resolve a rota e invoca o controller.
     */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        $handler = $this->router->match($method, $uri);

        if ($handler === null) {
            $this->renderNotFound();
            return;
        }

        [$controllerName, $action] = explode('@', $handler);
        $controllerClass = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
            $this->renderNotFound();
            return;
        }

        $controller = new $controllerClass($this->config);
        $controller->{$action}();
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        $config  = $this->config;
        $wedding = $this->config['wedding'] ?? [];
        $app     = $this->config['app'] ?? [];

        $notFound = APP_PATH . '/Views/errors/404.php';
        if (is_file($notFound)) {
            require APP_PATH . '/Views/layouts/header.php';
            require $notFound;
            require APP_PATH . '/Views/layouts/footer.php';
            return;
        }

        echo '404 — Página não encontrada.';
    }
}
