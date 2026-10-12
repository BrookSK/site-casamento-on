<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controller base.
 *
 * Oferece o método view() que renderiza uma página dentro do layout padrão
 * (header + conteúdo + footer).
 */
abstract class Controller
{
    /** Configuração da aplicação (carregada em App). */
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Renderiza uma view envolvida pelo layout (header/footer).
     *
     * @param string               $view Nome da view em app/Views (sem .php)
     * @param array<string, mixed> $data Dados disponíveis para a view
     */
    protected function view(string $view, array $data = []): void
    {
        $viewFile = APP_PATH . '/Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View não encontrada: ' . htmlspecialchars($view, ENT_QUOTES);
            return;
        }

        // Dados sempre disponíveis nas views.
        $config  = $this->config;
        $wedding = $this->config['wedding'] ?? [];
        $app     = $this->config['app'] ?? [];

        // Extrai os dados específicos da página como variáveis.
        extract($data, EXTR_SKIP);

        require APP_PATH . '/Views/layouts/header.php';
        require $viewFile;
        require APP_PATH . '/Views/layouts/footer.php';
    }

    /**
     * Renderiza uma view SEM o layout público (usada pelas telas de admin,
     * que têm o próprio HTML).
     *
     * @param string               $view Caminho da view (sem .php)
     * @param array<string, mixed> $data
     */
    protected function viewRaw(string $view, array $data = []): void
    {
        $viewFile = APP_PATH . '/Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View não encontrada: ' . htmlspecialchars($view, ENT_QUOTES);
            return;
        }

        $config = $this->config;
        $app    = $this->config['app'] ?? [];
        extract($data, EXTR_SKIP);

        require $viewFile;
    }

    /** Redireciona para um caminho interno e encerra a execução. */
    protected function redirect(string $path): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        header('Location: ' . $baseUrl . '/' . ltrim($path, '/'));
        exit;
    }
}
