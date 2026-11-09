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
        $this->enforceCanonicalHost();

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

    /**
     * Redireciona o "www." para o domínio do base_url (e vice-versa).
     *
     * Os formulários e links usam o base_url absoluto. Quem abria o site pelo
     * www tinha o cookie de sessão gravado só para o www; ao enviar um
     * formulário para o domínio sem www, o cookie não ia junto, o token CSRF
     * falhava e a página simplesmente "recarregava" (ex.: gerar o PIX).
     * Mantendo um único host, a sessão é sempre a mesma.
     */
    private function enforceCanonicalHost(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $baseUrl   = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        $canonical = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $host      = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);

        if ($canonical === '' || $host === '' || $host === $canonical) {
            return;
        }
        // Só trata a variação www/sem www do próprio domínio — localhost, IPs
        // e outros hosts (ex.: ambiente de teste) seguem intactos.
        if ($host !== 'www.' . $canonical && 'www.' . $host !== $canonical) {
            return;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        // 308 preserva o método/corpo de um POST que chegue pelo host antigo.
        $status = in_array($method, ['GET', 'HEAD'], true) ? 301 : 308;
        $uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        header('Location: ' . $baseUrl . '/' . ltrim($uri, '/'), true, $status);
        exit;
    }

    private function renderNotFound(): void
    {
        // Usa o Controller base para o layout receber os nomes editáveis do
        // casal (antes aparecia "Noiva & Noivo") e o header sólido.
        (new \App\Controllers\ErrorController($this->config))->notFound();
    }
}
